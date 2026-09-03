export type DashboardCard = {
    id: string;
    label: string;
    value: number;
};

export type DashboardChart = {
    labels: string[];
    datasets: {
        label: string;
        data: number[];
    }[];
};

export type DashboardCharts = {
    [key: string]: DashboardChart;
};

export type DashboardActivityType = 'attendance' | 'leave' | 'permission' | 'complaint';

export type DashboardActivityStatus = 'pending' | 'approved' | 'rejected' | 'present' | 'late';

export type DashboardActivity = {
    id: number;
    type: DashboardActivityType;
    user_name: string;
    title: string;
    status: DashboardActivityStatus | null;
    created_at: string | null;
};

export type DashboardActivities = DashboardActivity[];

export type DashboardVariants = 'admin' | 'user';

export type AttendanceStatus = 'present' | 'late' | 'absent' | null;

export type TodayAttendance = {
    check_in_time: string | null;
    check_out_time: string | null;
    attendance_status: AttendanceStatus;
};

export type AttendanceHistoryItem = {
    id: number;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    attendance_status: AttendanceStatus;
};

export type DashboardData = {
    greeting: string;
    date: string;
    dashboard_variant: DashboardVariants;
    cards: DashboardCard[];
    charts: DashboardCharts;
    activities: DashboardActivity[];
    today_attendance: TodayAttendance | null;
    attendance_history: AttendanceHistoryItem[];
};