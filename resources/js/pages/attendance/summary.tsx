import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, RotateCcw, SearchX } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { summary } from '@/routes/attendance';
import { exportMethod } from '@/routes/attendance/summary';
import type {
    AttendanceSummaryCounts,
    AttendanceSummaryFilters,
    AttendanceSummaryStatus,
    AttendanceSummaryUser,
} from '@/types';

type Props = {
    users: AttendanceSummaryUser[];
    dates: string[];
    summary: AttendanceSummaryCounts;
    filters: AttendanceSummaryFilters;
};

const statusStyles: Record<AttendanceSummaryStatus, string> = {
    H: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    A: 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    I: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    C: 'border-transparent bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    S: 'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    D: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
};

const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const legend: { status: AttendanceSummaryStatus; label: string }[] = [
    { status: 'H', label: 'Hadir' },
    { status: 'A', label: 'Absen' },
    { status: 'D', label: 'Dinas' },
    { status: 'I', label: 'Izin' },
    { status: 'C', label: 'Cuti' },
    { status: 'S', label: 'Sakit' },
];

export default function AttendanceSummary({
    users,
    dates,
    summary: summaryCounts,
    filters,
}: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [isLoading, setIsLoading] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    const dateHeaders = useMemo(
        () =>
            dates.map((date) => {
                const parsed = new Date(`${date}T00:00:00`);

                return {
                    date,
                    day: parsed.getDate().toString().padStart(2, '0'),
                    weekday: dayNames[parsed.getDay()],
                };
            }),
        [dates],
    );

    useEffect(() => {
        const stopStart = router.on('start', () => setIsLoading(true));
        const stopFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    function show() {
        router.get(
            summary(),
            {
                start_date: startDate || undefined,
                end_date: endDate || undefined,
            },
            { preserveState: false, preserveScroll: true },
        );
    }

    function exportExcel() {
        const query: Record<string, string> = {};

        if (startDate) {
            query.start_date = startDate;
        }

        if (endDate) {
            query.end_date = endDate;
        }

        setIsExporting(true);
        window.location.href = exportMethod({ query }).url;
        setTimeout(() => setIsExporting(false), 2000);
    }

    function reset() {
        setStartDate('');
        setEndDate('');

        router.get(summary(), {}, { preserveState: false, preserveScroll: true });
    }

    const statCards = [
        { id: 'total_users', label: 'Total Pegawai', value: summaryCounts.total_users },
        { id: 'hadir', label: 'Hadir', value: summaryCounts.hadir },
        { id: 'absen', label: 'Absen', value: summaryCounts.absen },
        { id: 'dinas', label: 'Dinas', value: summaryCounts.dinas },
        { id: 'izin', label: 'Izin', value: summaryCounts.izin },
        { id: 'cuti', label: 'Cuti', value: summaryCounts.cuti },
        { id: 'sakit', label: 'Sakit', value: summaryCounts.sakit },
    ];

    return (
        <>
            <Head title="Attendance Summary" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between bg-sky-400 text-white p-4 rounded-lg">
                    <Heading
                        title="Attendance Summary"
                        description="Rekap kehadiran pegawai dalam periode terpilih"
                    />
                </div>

                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="start-date">Tanggal Mulai</Label>
                                <Input
                                    id="start-date"
                                    type="date"
                                    value={startDate}
                                    onChange={(event) =>
                                        setStartDate(event.target.value)
                                    }
                                    className="w-44"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="end-date">Tanggal Akhir</Label>
                                <Input
                                    id="end-date"
                                    type="date"
                                    value={endDate}
                                    onChange={(event) =>
                                        setEndDate(event.target.value)
                                    }
                                    className="w-44"
                                />
                            </div>

                            <Button onClick={show} disabled={isLoading}>
                                Tampilkan
                            </Button>

                            <Button
                                variant="secondary"
                                onClick={reset}
                                disabled={isLoading}
                            >
                                <RotateCcw />
                                Reset
                            </Button>

                            <Button
                                variant="secondary"
                                onClick={exportExcel}
                                disabled={isLoading || isExporting}
                            >
                                {isExporting && <Spinner />}
                                <FileSpreadsheet />
                                Export Excel
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-4 xl:grid-cols-7">
                            {statCards.map((card) => (
                                <Card key={card.id}>
                                    <CardHeader>
                                        <CardDescription>
                                            {card.label}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-3xl font-semibold tracking-tight">
                                            {card.value}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <div className="flex flex-wrap items-center gap-4">
                            {legend.map((item) => (
                                <span
                                    key={item.status}
                                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                                >
                                    <Badge
                                        variant="outline"
                                        className={statusStyles[item.status]}
                                    >
                                        {item.status}
                                    </Badge>
                                    {item.label}
                                </span>
                            ))}
                        </div>

                        {users.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                <div className="rounded-full bg-muted p-3">
                                    <SearchX className="size-6 text-muted-foreground" />
                                </div>
                                <p className="font-medium">No employees found</p>
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada pegawai untuk periode ini.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <th className="sticky left-0 z-10 bg-card px-4 py-3 text-left">
                                                NIP
                                            </th>
                                            <th className="sticky left-28 z-10 bg-card px-4 py-3 text-left">
                                                Nama
                                            </th>
                                            <th className="sticky left-64 z-10 bg-card px-4 py-3 text-left">
                                                Position
                                            </th>
                                            {dateHeaders.map((header) => (
                                                <th
                                                    key={header.date}
                                                    className="px-1.5 py-3 text-center"
                                                >
                                                    <span className="block text-xs">
                                                        {header.day}
                                                    </span>
                                                    <span className="block text-[10px] font-normal text-muted-foreground">
                                                        {header.weekday}
                                                    </span>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {users.map((user) => (
                                            <tr
                                                key={user.nip}
                                                className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                            >
                                                <td className="sticky left-0 z-10 bg-card px-4 py-2 font-medium whitespace-nowrap">
                                                    {user.nip}
                                                </td>
                                                <td className="sticky left-28 z-10 bg-card px-4 py-2 font-medium whitespace-nowrap">
                                                    {user.name}
                                                </td>
                                                <td className="sticky left-64 z-10 bg-card px-4 py-2 text-muted-foreground whitespace-nowrap">
                                                    {user.position}
                                                </td>
                                                {dateHeaders.map((header) => (
                                                    <td
                                                        key={header.date}
                                                        className="px-1.5 py-2 text-center"
                                                    >
                                                        <Badge
                                                             variant="outline"
                                                             className={`h-6 w-6 justify-center px-0 ${statusStyles[user.dates[header.date] ?? 'A']}`}
                                                         >
                                                             {user.dates[
                                                                 header.date
                                                             ] ?? 'A'}
                                                        </Badge>
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AttendanceSummary.layout = {
    breadcrumbs: [
        {
            title: 'Attendance Summary',
            href: summary(),
        },
    ],
};