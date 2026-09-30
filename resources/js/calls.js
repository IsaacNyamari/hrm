/**
 * 1:1 and small-group calling over WebRTC. Reverb (via Laravel Echo) is used
 * purely for signalling — presence (who's online, who's in this call) and
 * exchanging SDP offers/answers + ICE candidates via presence-channel
 * whispers. No media ever touches the server: once two peers have exchanged
 * that handshake, audio/video flows directly between their browsers.
 *
 * This is a full mesh: every participant holds one RTCPeerConnection per
 * other participant. That's fine for 1:1 and small meetings (a handful of
 * people) but each participant's upload bandwidth is divided among everyone
 * else, so it degrades past a handful. A larger "meeting room" tier would
 * need a media relay (SFU) instead of a mesh — a deliberate later upgrade,
 * not something bolted on here.
 *
 * STUN-only (Google's public server) — no TURN relay is configured, so a
 * call between two peers on very restrictive NATs (e.g. some corporate
 * firewalls) may fail to connect. Adding a TURN server (self-hosted coturn,
 * or a hosted one) is the fix if that turns out to matter in practice.
 */

const ICE_SERVERS = [{urls: 'stun:stun.l.google.com:19302'}];

// Played to the caller while an outgoing call is ringing out (no one has
// joined yet). Browsers generally allow this without restriction since it's
// always started from a user gesture (clicking "Call").
const connectingSound = new Audio('/sounds/call-connecting.wav');
connectingSound.loop = true;

// Registering directly when Alpine is already on the page (true for every
// `wire:navigate` transition after the first) instead of only via
// `alpine:init` — that event fires once per browser session, so a page
// visited after the first would never register this component and
// `x-data="callRoom(...)"` would throw "callRoom is not defined".
function registerCallRoom() {
    Alpine.data('callRoom', (config) => ({
        userId: config.userId,
        userName: config.userName,
        userInitials: config.userInitials,
        tenantId: config.tenantId,

        onlineUsers: [],
        selectedIds: [],

        inCall: false,
        callUuid: null,
        joining: false,
        ringingOut: false,
        isInitiator: false,
        participants: {},
        micMuted: false,
        cameraOff: false,
        mediaError: null,

        localStream: null,
        peers: {},
        lobbyChannel: null,
        callChannel: null,

        showInvite: false,
        inviteSelectedIds: [],

        boot() {
            this.lobbyChannel = window.Echo.join(`tenant.${this.tenantId}.calls`)
                .here((users) => {
                    this.onlineUsers = users.filter((u) => u.id !== this.userId);
                })
                .joining((user) => {
                    if (user.id !== this.userId && ! this.onlineUsers.some((u) => u.id === user.id)) {
                        this.onlineUsers.push(user);
                    }
                })
                .leaving((user) => {
                    this.onlineUsers = this.onlineUsers.filter((u) => u.id !== user.id);
                });

            Livewire.on('call-started', ({uuid}) => this.joinCallRoom(uuid));

            if (config.initialCallUuid) {
                this.joinCallRoom(config.initialCallUuid);
            }

            window.addEventListener('beforeunload', () => {
                if (this.inCall) {
                    this.$wire.leaveCall();
                }
            });
            document.addEventListener('livewire:navigating', () => {
                if (this.inCall) {
                    this.leaveCall();
                }
            });
        },

        toggleSelected(userId) {
            this.selectedIds = this.selectedIds.includes(userId)
                ? this.selectedIds.filter((id) => id !== userId)
                : [...this.selectedIds, userId];
        },

        /**
         * Online colleagues not already in this call — candidates for the
         * "Add people" control while a call is under way.
         */
        get invitableUsers() {
            return this.onlineUsers.filter((u) => ! this.participants[u.id]);
        },

        toggleInviteSelected(userId) {
            this.inviteSelectedIds = this.inviteSelectedIds.includes(userId)
                ? this.inviteSelectedIds.filter((id) => id !== userId)
                : [...this.inviteSelectedIds, userId];
        },

        async inviteMore() {
            if (this.inviteSelectedIds.length === 0) {
                return;
            }

            await this.$wire.inviteToCall(this.inviteSelectedIds);
            this.inviteSelectedIds = [];
            this.showInvite = false;
        },

        async startCall() {
            if (this.selectedIds.length === 0) {
                return;
            }

            this.isInitiator = true;
            await this.$wire.startCall(this.selectedIds);
            this.selectedIds = [];
        },

        async joinCallRoom(uuid) {
            if (this.inCall && this.callUuid === uuid) {
                return;
            }

            this.joining = true;
            this.callUuid = uuid;

            const gotMedia = await this.startLocalMedia();

            if (! gotMedia) {
                this.joining = false;

                return;
            }

            this.inCall = true;
            this.joining = false;

            this.callChannel = window.Echo.join(`call.${uuid}`)
                .here((users) => {
                    const others = users.filter((user) => user.id !== this.userId);
                    others.forEach((user) => this.addParticipant(user));

                    if (this.isInitiator && others.length === 0) {
                        this.startRingingOut();
                    }
                })
                .joining((user) => {
                    this.stopRingingOut();
                    this.addParticipant(user);
                    this.createPeerConnection(user.id, true);
                })
                .leaving((user) => {
                    this.removeParticipant(user.id);
                })
                .listenForWhisper('signal', (payload) => this.handleSignal(payload))
                .listen('.call.declined', (e) => {
                    this.$dispatch('toast-show', {variant: 'info', text: `${e.name} declined the call.`});
                })
                .listen('.call.ended', () => this.teardown());
        },

        async startLocalMedia() {
            this.mediaError = null;

            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({audio: true, video: true});
            } catch (error) {
                this.mediaError = 'Could not access your camera/microphone. Check your browser permissions and try again.';

                return false;
            }

            this.$nextTick(() => {
                const el = document.getElementById('local-video');

                if (el) {
                    el.srcObject = this.localStream;
                }
            });

            return true;
        },

        startRingingOut() {
            this.ringingOut = true;
            connectingSound.currentTime = 0;
            connectingSound.play().catch(() => {});
        },

        stopRingingOut() {
            if (! this.ringingOut) {
                return;
            }

            this.ringingOut = false;
            connectingSound.pause();
            connectingSound.currentTime = 0;
        },

        addParticipant(user) {
            if (! this.participants[user.id]) {
                this.participants[user.id] = {id: user.id, name: user.name, initials: user.initials, hasStream: false};
            }
        },

        removeParticipant(userId) {
            const pc = this.peers[userId];

            if (pc) {
                pc.close();
                delete this.peers[userId];
            }

            delete this.participants[userId];
        },

        createPeerConnection(peerId, isInitiator) {
            const pc = new RTCPeerConnection({iceServers: ICE_SERVERS});

            this.localStream.getTracks().forEach((track) => pc.addTrack(track, this.localStream));

            pc.ontrack = (event) => {
                this.participants[peerId] ??= {id: peerId, hasStream: false};
                this.participants[peerId].hasStream = true;

                this.$nextTick(() => {
                    const el = document.getElementById(`remote-video-${peerId}`);

                    if (el) {
                        el.srcObject = event.streams[0];
                    }
                });
            };

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal(peerId, {type: 'ice', candidate: event.candidate});
                }
            };

            this.peers[peerId] = pc;

            if (isInitiator) {
                pc.createOffer()
                    .then((offer) => pc.setLocalDescription(offer).then(() => offer))
                    .then((offer) => this.sendSignal(peerId, {type: 'offer', sdp: offer}));
            }

            return pc;
        },

        sendSignal(toUserId, payload) {
            this.callChannel.whisper('signal', {from: this.userId, to: toUserId, ...payload});
        },

        async handleSignal(payload) {
            if (payload.to !== this.userId) {
                return;
            }

            let pc = this.peers[payload.from];

            if (payload.type === 'offer') {
                pc ??= this.createPeerConnection(payload.from, false);
                await pc.setRemoteDescription(payload.sdp);
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                this.sendSignal(payload.from, {type: 'answer', sdp: answer});

                return;
            }

            if (! pc) {
                return;
            }

            if (payload.type === 'answer') {
                await pc.setRemoteDescription(payload.sdp);
            } else if (payload.type === 'ice') {
                await pc.addIceCandidate(payload.candidate).catch(() => {});
            }
        },

        toggleMic() {
            this.micMuted = ! this.micMuted;
            this.localStream?.getAudioTracks().forEach((track) => (track.enabled = ! this.micMuted));
        },

        toggleCamera() {
            this.cameraOff = ! this.cameraOff;
            this.localStream?.getVideoTracks().forEach((track) => (track.enabled = ! this.cameraOff));
        },

        async leaveCall() {
            await this.$wire.leaveCall();
            this.teardown();
        },

        async endForEveryone() {
            await this.$wire.endCallForEveryone();
            this.teardown();
        },

        /**
         * Client-side cleanup only — closes every peer connection, stops the
         * local camera/mic, and leaves the Reverb channel. Callers that need
         * the server to know (a genuine hang-up, as opposed to the call
         * having already ended for everyone) call $wire.leaveCall()
         * themselves before this.
         */
        teardown() {
            this.stopRingingOut();

            if (! this.inCall && ! this.joining) {
                return;
            }

            Object.keys(this.peers).forEach((id) => this.removeParticipant(id));

            this.localStream?.getTracks().forEach((track) => track.stop());
            this.localStream = null;

            if (this.callChannel) {
                window.Echo.leave(`call.${this.callUuid}`);
                this.callChannel = null;
            }

            this.inCall = false;
            this.joining = false;
            this.callUuid = null;
            this.isInitiator = false;
            this.participants = {};
            this.micMuted = false;
            this.cameraOff = false;
        },
    }));
}

if (window.Alpine) {
    registerCallRoom();
} else {
    document.addEventListener('alpine:init', registerCallRoom);
}
