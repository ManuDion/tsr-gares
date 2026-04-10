@extends('layouts.app')

@section('title', 'Dashboard · TSR Gares Finance')
@section('heading', $heading)
@section('subheading', $subheading)

@section('content')
    <livewire:dashboard-overview />
@endsection
