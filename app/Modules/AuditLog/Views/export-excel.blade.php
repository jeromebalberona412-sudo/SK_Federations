<table border="1">
    <thead>
        <tr>
            <th>Date &amp; Time</th>
            <th>User</th>
            <th>Role</th>
            <th>Barangay</th>
            <th>Event Type</th>
            <th>Action</th>
            <th>Entity Type</th>
            <th>Entity ID</th>
            <th>IP Address</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['created_at'] }}</td>
                <td>{{ $row['user_name'] }}</td>
                <td>{{ $row['role'] }}</td>
                <td>{{ $row['barangay'] }}</td>
                <td>{{ $row['event_type'] }}</td>
                <td>{{ $row['action'] }}</td>
                <td>{{ $row['entity_type'] }}</td>
                <td>{{ $row['entity_id'] }}</td>
                <td>{{ $row['ip_address'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
