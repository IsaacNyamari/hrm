@extends('errors.layout')

@section('code', '419')
@section('title', __('Session expired'))
@section('message', __('Your session timed out for security. Please refresh the page and try again — anything you typed may need to be re-entered.'))
@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
@endsection

@section('actions')
    <button type="button" class="btn primary" onclick="location.reload()">{{ __('Try again') }}</button>
    <a class="btn ghost" href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
@endsection
