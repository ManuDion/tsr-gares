@extends('layouts.app')

@section('title', 'Dashboard · Progiciel TSR')
@section('heading', $heading)
@section('subheading', $subheading)

@section('content')
    <livewire:dashboard-overview :module="$module->value" :key="'dashboard-'.$module->value" />
@endsection
