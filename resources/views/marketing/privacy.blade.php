@php($contact = config('platform.contact'))
@php($appName = config('app.name'))
@php($updated = '22 September 2026')

<x-layouts::marketing :title="__('Privacy Policy')" :description="__('How :app collects, uses and protects personal data on the web and in the mobile app.', ['app' => $appName])">
    <x-marketing.page-header
        :eyebrow="__('Legal')"
        :title="__('Privacy Policy')"
        :subtitle="__('How we collect, use and protect personal data across the :app website, web app and mobile app.', ['app' => $appName])" />

    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[14rem_1fr] lg:px-8">
            @php($sections = [
                'summary' => __('Summary'),
                'who-we-are' => __('Who we are'),
                'data-we-collect' => __('Data we collect'),
                'mobile-app' => __('The mobile app'),
                'how-we-use' => __('How we use data'),
                'sharing' => __('Sharing'),
                'retention' => __('Retention'),
                'security' => __('Security'),
                'your-rights' => __('Your rights'),
                'children' => __('Children'),
                'changes' => __('Changes'),
                'contact-us' => __('Contact us'),
            ])

            <nav class="hidden lg:block" aria-label="{{ __('On this page') }}">
                <div class="sticky top-28">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-400">{{ __('On this page') }}</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($sections as $id => $label)
                            <li><a href="#{{ $id }}" class="text-zinc-500 transition hover:text-brand">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </nav>

            <article class="min-w-0 space-y-12 text-[15px] leading-relaxed text-zinc-600 [&_h2]:scroll-mt-28 [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:tracking-tight [&_h2]:text-night [&_h3]:mt-6 [&_h3]:font-bold [&_h3]:text-night [&_li]:pl-1 [&_p]:mt-4 [&_strong]:text-night [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5" data-test="privacy-policy">
                <p class="!mt-0 text-sm text-zinc-400">{{ __('Last updated: :date', ['date' => $updated]) }}</p>

                <div id="summary" class="rounded-2xl bg-canvas p-6 sm:p-8">
                    <h2 id="summary-title">{{ __('The short version') }}</h2>
                    <ul>
                        <li>{{ __('Your employer decides what HR data is kept about you. We store and process it on their behalf.') }}</li>
                        <li>{{ __('The mobile app reads your location only when you clock in or out, and only if you allow it.') }}</li>
                        <li>{{ __('We do not sell personal data, show adverts or use advertising trackers.') }}</li>
                        <li>{{ __('Each organisation\'s data is kept separate. Sensitive fields are encrypted, and every sign-in and change is logged.') }}</li>
                        <li>{{ __('You can ask to see, correct or delete your data under the Kenya Data Protection Act, 2019.') }}</li>
                    </ul>
                </div>

                <section>
                    <h2 id="who-we-are">{{ __('1. Who we are') }}</h2>
                    <p>{{ __(':app is a human resources, attendance and payroll platform used by organisations (our "customers") to manage their people. This policy covers our website, the web application and the :app mobile app for Android and iOS.', ['app' => $appName]) }}</p>
                    <p><strong>{{ __('Your employer is in charge of your HR data.') }}</strong> {{ __('When an organisation uses :app to manage its staff, that organisation is the data controller: it decides what is recorded about you and why. We act as its data processor and handle that data only on its instructions and to provide the service.', ['app' => $appName]) }}</p>
                    <p>{{ __('We are the data controller for the data we collect for our own purposes: customer account and billing details, enquiries sent through our website, and the security and service logs we keep to run the platform.') }}</p>
                </section>

                <section>
                    <h2 id="data-we-collect">{{ __('2. Data we collect') }}</h2>

                    <h3>{{ __('Account and sign-in') }}</h3>
                    <ul>
                        <li>{{ __('Name, work email address, password (stored only as a one-way hash) and two-factor authentication settings.') }}</li>
                        <li>{{ __('Your organisation, role and permissions.') }}</li>
                        <li>{{ __('Sign-in history: date, time, IP address and device or browser name. This helps you see and sign out other sessions.') }}</li>
                    </ul>

                    <h3>{{ __('Employment records (entered by your employer)') }}</h3>
                    <ul>
                        <li>{{ __('Personal details such as date of birth, gender, phone number, address, national ID and next of kin.') }}</li>
                        <li>{{ __('Job details such as department, job title, manager, employment dates and contract type.') }}</li>
                        <li>{{ __('Statutory and payment details: KRA PIN, NSSF and SHIF numbers, bank or M-Pesa details, salary and allowances.') }}</li>
                        <li>{{ __('Payslips, loans and advances, expense claims, and the documents and letters your employer issues.') }}</li>
                        <li>{{ __('Leave requests and balances, tasks, performance reviews, goals and announcements.') }}</li>
                    </ul>

                    <h3>{{ __('Attendance') }}</h3>
                    <ul>
                        <li>{{ __('Clock-in, clock-out and break times, and how each was recorded (web, mobile app, or a biometric device at your workplace).') }}</li>
                        <li>{{ __('When your employer uses location-checked attendance: your approximate location at the moment you clock in or out.') }}</li>
                        <li>{{ __('For biometric devices at your workplace: your enrolled device user number. Your fingerprint or face template stays on the device and is not sent to us.') }}</li>
                    </ul>

                    <h3>{{ __('Job applicants') }}</h3>
                    <ul>
                        <li>{{ __('Your name, email, phone number, CV or other files you upload, and your answers on the application form.') }}</li>
                        <li>{{ __('Your application tracking code, and the stage and outcome your prospective employer records.') }}</li>
                    </ul>

                    <h3>{{ __('Technical data') }}</h3>
                    <ul>
                        <li>{{ __('Server logs with IP address, request time and error details. We use them to keep the service secure and working.') }}</li>
                        <li>{{ __('Essential cookies for your session, sign-in protection and security. We do not use advertising or cross-site tracking cookies.') }}</li>
                    </ul>
                </section>

                <section>
                    <h2 id="mobile-app">{{ __('3. The mobile app') }}</h2>
                    <p>{{ __('The app shows the same information you can see on the web, and it only asks for a device permission when you use the feature that needs it. You can change any permission at any time in your phone\'s settings.') }}</p>
                    <ul>
                        <li><strong>{{ __('Location') }}</strong> {{ __('is read only when you tap clock in or clock out, and only if your employer requires it. It is sent with that one punch. The app does not track your location in the background.') }}</li>
                        <li><strong>{{ __('Notifications') }}</strong>: {{ __('if you allow them, we store a push token for your device and its platform (Android or iOS). We use them to tell you about leave decisions, payslips, tasks, announcements and attendance. Push messages are delivered through Expo, Apple and Google. Signing out removes the token.') }}</li>
                        <li><strong>{{ __('Camera, photos and files') }}</strong> {{ __('are used only when you choose to attach something, such as a medical note for sick leave. Only the file you pick is uploaded.') }}</li>
                        <li><strong>{{ __('Face ID, Touch ID or fingerprint unlock') }}</strong> {{ __('is handled entirely by your phone. We never receive or store your biometric data.') }}</li>
                        <li><strong>{{ __('Sign-in tokens') }}</strong> {{ __('are kept in your phone\'s secure storage (Keychain or Keystore). Each device appears in your active sessions, where you can revoke it.') }}</li>
                        <li><strong>{{ __('Downloaded payslips') }}</strong> {{ __('are saved to your device only when you choose to download or share them.') }}</li>
                    </ul>
                    <p>{{ __('The app contains no advertising or third-party analytics SDKs.') }}</p>
                </section>

                <section>
                    <h2 id="how-we-use">{{ __('4. How we use data') }}</h2>
                    <ul>
                        <li>{{ __('To provide the service your employer has set up: records, attendance, leave, payroll and payslips, tasks, performance, recruitment and self-service.') }}</li>
                        <li>{{ __('To send the emails, in-app messages and push notifications the service depends on, such as password resets, approvals, payslips and application updates.') }}</li>
                        <li>{{ __('To keep accounts and data secure, prevent fraud and abuse, and investigate problems.') }}</li>
                        <li>{{ __('To bill our customers and meet our legal, tax and accounting obligations.') }}</li>
                        <li>{{ __('To reply to enquiries and quote requests sent through our website.') }}</li>
                    </ul>
                    <p>{{ __('We process data to perform our contract with the customer, to meet legal obligations, for our legitimate interest in running a secure service, or with consent where the law requires it (for example, device location and notifications). We do not use your data for automated decisions that have legal or similarly significant effects on you. Pay is calculated from the rules your employer sets, and your employer reviews and approves it.') }}</p>
                </section>

                <section>
                    <h2 id="sharing">{{ __('5. Sharing') }}</h2>
                    <p>{{ __('We do not sell or rent personal data. We share it only as follows:') }}</p>
                    <ul>
                        <li><strong>{{ __('Your employer') }}</strong> {{ __('and the people it authorises. Access follows the roles your employer assigns, for example HR, payroll or your line manager.') }}</li>
                        <li><strong>{{ __('Service providers') }}</strong> {{ __('who help us run the platform under confidentiality and data protection terms: server hosting, email delivery, push notifications (Expo, Apple and Google) and payment processing (M-Pesa) for subscription billing.') }}</li>
                        <li><strong>{{ __('Integrations your employer connects') }}</strong>, {{ __('such as biometric attendance devices or its own systems through our API.') }}</li>
                        <li><strong>{{ __('Authorities') }}</strong> {{ __('when the law requires it, or to protect the rights, safety and property of our users or the public.') }}</li>
                    </ul>
                    <p>{{ __('Some service providers may process data outside Kenya. When that happens, we make sure there are appropriate safeguards, as the Data Protection Act requires.') }}</p>
                </section>

                <section>
                    <h2 id="retention">{{ __('6. Retention') }}</h2>
                    <p>{{ __('We keep customer data for as long as the organisation has an active account, or as long as it instructs us. Employment and payroll records are often kept for several years after employment ends, because tax and labour laws require it. Your employer sets and is responsible for those periods.') }}</p>
                    <p>{{ __('When a customer closes its account, we delete or anonymise its data within a reasonable period, unless we must keep it by law. Push tokens are deleted when you sign out. Server logs are kept only as long as needed for security.') }}</p>
                </section>

                <section>
                    <h2 id="security">{{ __('7. Security') }}</h2>
                    <ul>
                        <li>{{ __('All traffic uses HTTPS. Each organisation\'s data is logically separated from every other organisation\'s.') }}</li>
                        <li>{{ __('Sensitive fields, such as identification and bank details, are encrypted at rest.') }}</li>
                        <li>{{ __('Role-based access, optional two-factor authentication and passkeys, and a full audit trail of important changes.') }}</li>
                        <li>{{ __('Mobile sign-in tokens can be revoked from any signed-in device.') }}</li>
                    </ul>
                    <p>{{ __('No system is perfectly secure. If we become aware of a breach that affects your data, we will notify the affected customers and, where required, the Office of the Data Protection Commissioner.') }}</p>
                </section>

                <section>
                    <h2 id="your-rights">{{ __('8. Your rights') }}</h2>
                    <p>{{ __('Under the Kenya Data Protection Act, 2019, you have the right to:') }}</p>
                    <ul>
                        <li>{{ __('be told how your data is used, and to see a copy of it;') }}</li>
                        <li>{{ __('have wrong or incomplete data corrected;') }}</li>
                        <li>{{ __('have data deleted when there is no longer a lawful reason to keep it;') }}</li>
                        <li>{{ __('object to or restrict certain processing, and withdraw consent (for example, by turning off location or notifications);') }}</li>
                        <li>{{ __('receive your data in a portable format where this applies.') }}</li>
                    </ul>
                    <p>{{ __('If you are an employee, please send requests to your employer\'s HR team first, because they control your records. We will help them respond. For anything else, or if you are unsure who to contact, write to us using the details below. You may also complain to the Office of the Data Protection Commissioner (odpc.go.ke).') }}</p>
                    <p>{{ __('To delete your account, ask your employer\'s HR team or contact us. Some records may have to be kept for the legal periods described above.') }}</p>
                </section>

                <section>
                    <h2 id="children">{{ __('9. Children') }}</h2>
                    <p>{{ __(':app is a workplace tool and is not intended for children under 18. We do not knowingly collect their personal data.', ['app' => $appName]) }}</p>
                </section>

                <section>
                    <h2 id="changes">{{ __('10. Changes to this policy') }}</h2>
                    <p>{{ __('We may update this policy as the service changes. The date at the top shows when it was last revised. For significant changes, we will let customers know in advance through the app or by email.') }}</p>
                </section>

                <section>
                    <h2 id="contact-us">{{ __('11. Contact us') }}</h2>
                    <p>{{ __('For questions about this policy or your data:') }}</p>
                    <ul>
                        @if ($contact['email'])
                            <li>{{ __('Email:') }} <a href="mailto:{{ $contact['email'] }}" class="font-semibold text-brand hover:underline">{{ $contact['email'] }}</a></li>
                        @endif
                        @if ($contact['phone'])
                            <li>{{ __('Phone:') }} <a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="font-semibold text-brand hover:underline">{{ $contact['phone'] }}</a></li>
                        @endif
                        @if ($contact['location'])
                            <li>{{ __('Location:') }} {{ $contact['location'] }}</li>
                        @endif
                        <li>{{ __('Or use our') }} <a href="{{ route('contact') }}" class="font-semibold text-brand hover:underline">{{ __('contact form') }}</a>.</li>
                    </ul>
                </section>
            </article>
        </div>
    </section>
</x-layouts::marketing>
