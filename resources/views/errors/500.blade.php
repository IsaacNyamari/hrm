@extends('errors.layout')

@section('code', '500')
@section('title', __('Something went wrong'))
@section('message', __('An unexpected error occurred on our side. It has been logged and we\'ll look into it. Please try again in a moment.'))
@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
@endsection

@section('actions')
    <button type="button" class="btn primary" onclick="location.reload()">{{ __('Try again') }}</button>
    <a class="btn ghost" href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
@endsection

@section('hint', __('If this keeps happening, contact support and mention the time it occurred.'))
