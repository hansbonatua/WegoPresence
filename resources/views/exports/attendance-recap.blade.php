<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance Recap</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 2px 0; color: #0c4a6e; }
        .subtitle { font-size: 13px; margin: 0 0 4px 0; }
        .period { font-size: 10px; color: #4b5563; margin: 0 0 12px 0; }
        .generated { font-size: 9px; color: #6b7280; margin: 0 0 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background: #e0f2fe; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>WegoPresence</h1>
    <p class="subtitle"><strong>Attendance Recap</strong></p>
    <p class="period">Period: {{ $period }}</p>
    <p class="generated">Generated at: {{ $generatedAt }} · {{ count($rows) }} record(s)</p>

    <table>
        <thead>
            <tr>
                <th style="width: 4%">No</th>
                <th style="width: 12%">NIP</th>
                <th style="width: 18%">Nama</th>
                <th style="width: 17%">Office</th>
                <th style="width: 11%">Tanggal</th>
                <th style="width: 8%">Check In</th>
                <th style="width: 8%">Check Out</th>
                <th style="width: 10%">Status</th>
                <th style="width: 12%">Late (min)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['nip'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['office'] }}</td>
                    <td>{{ $row['attendance_date'] }}</td>
                    <td>{{ $row['check_in_time'] }}</td>
                    <td>{{ $row['check_out_time'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['late_minutes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 16px;">
                        No attendance records found for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
