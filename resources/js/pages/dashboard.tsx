import { Head, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type {
    AttendanceHistoryItem,
    DashboardActivity,
    DashboardCard,
    DashboardChart,
    DashboardData,
    TodayAttendance,
} from '@/types';

type Props = DashboardData;

const statusStyles: Record<string, string> = {
    pending:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    approved:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    rejected:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    late: 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    on_time:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
};

const statusLabels: Record<string, string> = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
    late: 'Late',
    on_time: 'On time',
};

function statusLabel(status: string | null): string {
    return status ? (statusLabels[status] ?? status) : '—';
}

function statusStyle(status: string | null): string {
    return status ? (statusStyles[status] ?? '') : '';
}

function CardGrid({ cards }: { cards: DashboardCard[] }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => (
                <Card key={card.id}>
                    <CardHeader>
                        <CardDescription>{card.label}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-semibold tracking-tight">
                            {card.value}
                        </p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function AttendanceTrendChart({ chart }: { chart: DashboardChart }) {
    const data = chart?.datasets[0]?.data ?? [];
    const labels = chart?.labels ?? [];
    const max = Math.max(...data, 1);
    const hasData = data.some((value) => value > 0);

    return (
        <Card className="min-h-64">
            <CardHeader>
                <CardTitle>Attendance Trend</CardTitle>
                <CardDescription>Last 7 days</CardDescription>
            </CardHeader>
            <CardContent>
                {!hasData ? (
                    <div className="flex min-h-40 flex-col items-center justify-center gap-1 text-center">
                        <p className="text-sm font-medium">
                            No attendance data yet
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Check-ins from the last 7 days will appear here as
                            a bar chart.
                        </p>
                    </div>
                ) : (
                    <div className="flex h-40 items-end gap-2">
                        {data.map((value, index) => (
                            <div
                                key={labels[index] ?? index}
                                title={`${labels[index] ?? ''}: ${value}`}
                                className="group flex min-w-0 flex-1 flex-col items-center gap-2"
                            >
                                <p className="text-xs font-medium text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100">
                                    {value}
                                </p>
                                <div
                                    className={`w-full rounded-t-md bg-primary/80 transition-colors group-hover:bg-primary ${value === 0 ? 'h-0.5' : ''}`}
                                    style={{
                                        height: value === 0 ? undefined : `${Math.max((value / max) * 100, 4)}%`,
                                    }}
                                />
                                <p className="text-[10px] text-muted-foreground">
                                    {(labels[index] ?? '').slice(5)}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function RecentActivity({ activities }: { activities: DashboardActivity[] }) {
    return (
        <Card className="min-h-64">
            <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {activities.length === 0 ? (
                    <div className="flex min-h-40 flex-col items-center justify-center gap-1 text-center">
                        <p className="text-sm font-medium">No recent activity</p>
                        <p className="text-sm text-muted-foreground">
                            New attendance, leave, permission and complaint
                            events will show up here.
                        </p>
                    </div>
                ) : (
                    activities.map((activity) => (
                        <div
                            key={`${activity.type}-${activity.id}`}
                            className="flex items-center justify-between gap-4"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {activity.user_name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {activity.title}
                                </p>
                                <p className="text-xs text-muted-foreground/70">
                                    {activity.created_at?.slice(0, 10)}
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className={statusStyle(activity.status)}
                            >
                                {statusLabel(activity.status)}
                            </Badge>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function TodayStatusCards({ today }: { today: TodayAttendance }) {
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>Today's Attendance Status</CardDescription>
                </CardHeader>
                <CardContent>
                    {today.attendance_status ? (
                        <Badge
                            variant="outline"
                            className={statusStyle(today.attendance_status)}
                        >
                            {statusLabel(today.attendance_status)}
                        </Badge>
                    ) : (
                        <p className="text-2xl font-semibold tracking-tight text-muted-foreground">
                            Not marked yet
                        </p>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Check In</CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-3xl font-semibold tracking-tight">
                        {today.check_in_time ?? '—'}
                    </p>
                    {!today.check_in_time && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            You have not checked in today.
                        </p>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Check Out</CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-3xl font-semibold tracking-tight">
                        {today.check_out_time ?? '—'}
                    </p>
                    {!today.check_out_time && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            You have not checked out today.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function AttendanceHistory({ items }: { items: AttendanceHistoryItem[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Attendance History</CardTitle>
                <CardDescription>Your last 5 records</CardDescription>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <div className="flex min-h-32 flex-col items-center justify-center gap-1 text-center">
                        <p className="text-sm font-medium">No records yet</p>
                        <p className="text-sm text-muted-foreground">
                            Your attendance history will appear here after your
                            first check-in.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="pb-2 pr-4 font-medium">
                                        Date
                                    </th>
                                    <th className="pb-2 pr-4 font-medium">
                                        Check In
                                    </th>
                                    <th className="pb-2 pr-4 font-medium">
                                        Check Out
                                    </th>
                                    <th className="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="py-2.5 pr-4">
                                            {item.attendance_date}
                                        </td>
                                        <td className="py-2.5 pr-4">
                                            {item.check_in_time ?? '—'}
                                        </td>
                                        <td className="py-2.5 pr-4">
                                            {item.check_out_time ?? '—'}
                                        </td>
                                        <td className="py-2.5">
                                            <Badge
                                                variant="outline"
                                                className={statusStyle(
                                                    item.attendance_status,
                                                )}
                                            >
                                                {statusLabel(
                                                    item.attendance_status,
                                                )}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    greeting,
    date,
    dashboard_variant,
    cards,
    charts,
    activities,
    today_attendance,
    attendance_history,
}: Props) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];
    const chart = charts['attendance_trend'];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {greeting}, {firstName}
                    </h1>
                    <p className="text-sm text-muted-foreground">{date}</p>
                </div>

                {dashboard_variant === 'user' ? (
                    <>
                        {today_attendance && (
                            <TodayStatusCards today={today_attendance} />
                        )}
                        <AttendanceHistory items={attendance_history} />
                    </>
                ) : (
                    <>
                        <CardGrid cards={cards} />
                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="lg:col-span-2">
                                {chart ? (
                                    <AttendanceTrendChart chart={chart} />
                                ) : (
                                    <Card className="min-h-64">
                                        <CardHeader>
                                            <CardTitle>
                                                Attendance Trend
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-sm text-muted-foreground">
                                                No attendance data yet.
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                            <RecentActivity activities={activities} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
