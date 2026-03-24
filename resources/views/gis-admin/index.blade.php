@extends('layouts.gis-admin')

@section('content')
<div id="gis-admin-app"
     data-app-name="GIS Admin"
     data-logout-url="{{ route('gis-admin.logout') }}"
     data-user='@json(auth()->user())'>
</div>
@endsection