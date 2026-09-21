@extends('errors.layout')

@section('code', '402')
@section('title', __('Payment required'))
@section('message', __('Your organization\'s subscription needs attention before this area is available again. No data has been lost.'))
@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg>
@endsection

@section('actions')
    <a class="btn primary" href="{{ url('/billing') }}">{{ __('Go to billing') }}</a>
    <a class="btn ghost" href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
@endsection
