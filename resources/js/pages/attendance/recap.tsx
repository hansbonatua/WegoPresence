import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    FileSpreadsheet,
    FileText,
    MapPin,
    Search,
    SearchX,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { recap } from '@/routes/attendance';
import {
    excel as exportExcel,
    pdf as exportPdf,
} from '@/routes/attendance/recap/export';
import type {
    AttendanceRecapFilters,
    AttendanceRecapResource,
    OfficeResource,
    Paginated,
} from '@/types';

type RecapProps = {
    recaps: Paginated<AttendanceRecapResource>;
    filters: AttendanceRecapFilters;
    offices: OfficeResource[];
};

type RecapStatus = 'present' | 'late';

const statusStyles: Record<RecapStatus, string> = {
    present:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    late: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
};

function StatusBadge({
    status,
    lateMinutes,
}: {
    status: RecapStatus;
    lateMinutes: number | null;
}) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {status === 'present'
                ? 'Hadir'
                : `Terlambat · ${lateMinutes ?? 0} min`}
        </Badge>
    );
}

export default function AttendanceRecap({
    recaps,
    filters,
    offices,
}: RecapProps) {
    const { auth } = usePage().props;
    const isManager = auth.role === 'admin' || auth.role === 'super_admin';

    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [search, setSearch] = useState(filters.search);
    const [officeId, setOfficeId] = useState(filters.office_id);
    const [status, setStatus] = useState<RecapStatus | ''>(
        filters.attendance_status,
    );
    const [isLoading, setIsLoading] = useState(false);
    const [exporting, setExporting] = useState<'excel' | 'pdf' | null>(null);

    const activeFilters =
        filters.start_date ||
        filters.end_date ||
        filters.search ||
        filters.office_id ||
        filters.attendance_status;

    function runExport(format: 'excel' | 'pdf') {
        const query: Record<string, string> = {};

        if (search) {
            query.search = search;
        }

        if (startDate) {
            query.start_date = startDate;
        }

        if (endDate) {
            query.end_date = endDate;
        }

        if (officeId) {
            query.office_id = officeId;
        }

        if (status) {
            query.attendance_status = status;
        }

        const url =
            format === 'excel'
                ? exportExcel({ query }).url
                : exportPdf({ query }).url;

        setExporting(format);
        window.location.href = url;
        window.setTimeout(() => setExporting(null), 2000);
    }

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (
                search === filters.search &&
                startDate === filters.start_date &&
                endDate === filters.end_date &&
                officeId === filters.office_id &&
                status === filters.attendance_status
            ) {
                return;
            }

            router.get(
                recap(),
                {
                    search: search || undefined,
                    start_date: startDate || undefined,
                    end_date: endDate || undefined,
                    office_id: officeId || undefined,
                    attendance_status: status || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['recaps', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [
        search,
        startDate,
        endDate,
        officeId,
        status,
        filters.search,
        filters.start_date,
        filters.end_date,
        filters.office_id,
        filters.attendance_status,
    ]);

    useEffect(() => {
        const stopStart = router.on('start', () => setIsLoading(true));
        const stopFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    function resetFilters() {
        setSearch('');
        setStartDate('');
        setEndDate('');
        setOfficeId('');
        setStatus('');
    }

    return (
        <>
            <Head title="Attendance Recap" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 bg-sky-400 p-4 rounded-lg text-white sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Attendance Recap"
                        description="Review all attendance records across your office"
                    />

                    {isManager && (
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => runExport('excel')}
                                disabled={exporting !== null}
                            >
                                {exporting === 'excel' ? (
                                    <Spinner />
                                ) : (
                                    <FileSpreadsheet />
                                )}
                                Export Excel
                            </Button>

                            <Button
                                variant="secondary"
                                onClick={() => runExport('pdf')}
                                disabled={exporting !== null}
                            >
                                {exporting === 'pdf' ? (
                                    <Spinner />
                                ) : (
                                    <FileText />
                                )}
                                Export PDF
                            </Button>
                        </div>
                    )}
                </div>

                <Card>
                    <CardHeader className="gap-4">
                        <div>
                            <CardTitle>Attendance records</CardTitle>
                            <CardDescription>
                                {recaps.total > 0
                                    ? `${recaps.total} record${recaps.total === 1 ? '' : 's'} found`
                                    : 'No attendance records found'}
                            </CardDescription>
                        </div>

                        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
                            <div className="relative w-full lg:max-w-56">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search NIP or name..."
                                    className="pl-9"
                                    aria-label="Search by NIP or name"
                                />
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div className="relative">
                                    <CalendarRange className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="date"
                                        value={startDate}
                                        onChange={(event) =>
                                            setStartDate(event.target.value)
                                        }
                                        className="pl-9"
                                        aria-label="Start date"
                                    />
                                </div>
                                <span className="hidden text-sm text-muted-foreground sm:inline">
                                    to
                                </span>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(event) =>
                                        setEndDate(event.target.value)
                                    }
                                    aria-label="End date"
                                />
                            </div>

                            <Select
                                value={officeId}
                                onValueChange={setOfficeId}
                            >
                                <SelectTrigger
                                    className="w-full lg:w-44"
                                    aria-label="Filter by office"
                                >
                                    <SelectValue placeholder="All offices" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">
                                        All offices
                                    </SelectItem>
                                    {offices.map((office) => (
                                        <SelectItem
                                            key={office.id}
                                            value={String(office.id)}
                                        >
                                            {office.office_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(value as RecapStatus | '')
                                }
                            >
                                <SelectTrigger
                                    className="w-full lg:w-36"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="present">
                                        Hadir
                                    </SelectItem>
                                    <SelectItem value="late">
                                        Terlambat
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            {activeFilters && (
                                <Button
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th className="px-4 py-3">NIP</th>
                                        <th className="px-4 py-3">Name</th>
                                        <th className="px-4 py-3">Office</th>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">
                                            Check in
                                        </th>
                                        <th className="px-4 py-3">
                                            Check out
                                        </th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">
                                            Location
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {isLoading ? (
                                        Array.from({ length: 8 }).map(
                                            (_, index) => (
                                                <tr
                                                    key={index}
                                                    className="border-b"
                                                >
                                                    <td
                                                        colSpan={8}
                                                        className="px-4 py-4"
                                                    >
                                                        <Skeleton className="h-8 w-full" />
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : recaps.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={8}>
                                                <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                                    <div className="rounded-full bg-muted p-3">
                                                        <SearchX className="size-6 text-muted-foreground" />
                                                    </div>
                                                    <p className="font-medium">
                                                        {activeFilters
                                                            ? 'No records match your filters'
                                                            : 'No attendance records yet'}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {activeFilters
                                                            ? 'Try adjusting your search or filter options.'
                                                            : 'Attendance records will appear here once employees check in.'}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        recaps.data.map((record) => (
                                            <tr
                                                key={record.id}
                                                className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {record.user?.nip ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {record.user?.name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {record.office
                                                        ?.office_name ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {record.attendance_date}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {record.check_in_time ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {record.check_out_time ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {record.attendance_status ===
                                                        'present' ||
                                                    record.attendance_status ===
                                                        'late' ? (
                                                        <StatusBadge
                                                            status={
                                                                record.attendance_status
                                                            }
                                                            lateMinutes={
                                                                record.late_minutes
                                                            }
                                                        />
                                                    ) : (
                                                        '\u2014'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {record.latitude &&
                                                    record.longitude ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <MapPin className="size-3.5" />
                                                            {Number(
                                                                record.latitude,
                                                            ).toFixed(4)}
                                                            ,{' '}
                                                            {Number(
                                                                record.longitude,
                                                            ).toFixed(4)}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {recaps.data.length > 0 && (
                            <div className="border-t p-4">
                                <Pagination links={recaps.links} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AttendanceRecap.layout = {
    breadcrumbs: [
        {
            title: 'Attendance Recap',
            href: recap(),
        },
    ],
};
