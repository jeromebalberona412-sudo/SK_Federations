@foreach ($barangayZones ?? [] as $zone)
    <option value="{{ $zone->name }}">{{ $zone->name }}</option>
@endforeach
