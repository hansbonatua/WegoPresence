import { Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarCheck,
    CalendarOff,
    CalendarRange,
    ClipboardList,
    LayoutGrid,
    MessageSquareWarning,
    Table2,
    Thermometer,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as attendanceComplaintsIndex } from '@/routes/attendance-complaints';
import { index as attendanceIndex, recap as attendanceRecap, summary as attendanceSummary } from '@/routes/attendance';
import { index as businessTripsIndex } from '@/routes/business-trips';
import { index as leavesIndex } from '@/routes/leaves';
import { index as permissionsIndex } from '@/routes/permissions';
import { dashboard } from '@/routes';
import { index as sickLeavesIndex } from '@/routes/sick-leaves';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, pending_registrations_count } = usePage().props;
    const isManager = auth.role === 'admin' || auth.role === 'super_admin';
    const isAdmin = auth.role === 'admin';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Attendance',
            href: attendanceIndex(),
            icon: CalendarCheck,
        },
        ...(isManager
            ? [
                  {
                      title: 'Attendance Recap',
                      href: attendanceRecap(),
                      icon: Table2,
                  },
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: 'Attendance Summary',
                      href: attendanceSummary(),
                      icon: CalendarRange,
                  },
              ]
            : []),
        {
            title: 'Permissions',
            href: permissionsIndex(),
            icon: ClipboardList,
        },
        {
            title: 'Leave',
            href: leavesIndex(),
            icon: CalendarOff,
        },
        {
            title: 'Sick Leave',
            href: sickLeavesIndex(),
            icon: Thermometer,
        },
        {
            title: 'Pengajuan Dinas',
            href: businessTripsIndex(),
            icon: BriefcaseBusiness,
        },
        {
            title: 'Attendance Complaints',
            href: attendanceComplaintsIndex(),
            icon: MessageSquareWarning,
        },
        ...(isManager
            ? [
                  {
                      title: 'Users',
                      href: usersIndex(),
                      icon: Users,
                      badge:
                          typeof pending_registrations_count === 'number' &&
                          pending_registrations_count > 0
                              ? pending_registrations_count
                              : undefined,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
