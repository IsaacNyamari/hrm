@extends('errors.layout')

@section('code', '429')
@section('title', __('Too many requests'))
@section('message', __('You\'re going a little fast. Please wait a minute and try again.'))
@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
@endsection

@section('actions')
    <button type="button" class="btn primary" onclick="location.reload()">{{ __('Try again') }}</button>
    <a class="btn ghost" href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
@endsection
