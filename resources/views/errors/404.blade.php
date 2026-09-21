@extends('errors.layout')

@section('code', '404')
@section('title', __('Page not found'))
@section('message', __('The page you are looking for doesn\'t exist or has been moved. Check the address, or head back to safety.'))
@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
@endsection
