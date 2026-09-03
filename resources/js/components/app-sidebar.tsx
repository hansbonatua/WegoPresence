import { Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarCheck,
    CalendarOff,
    CalendarRange,
    ClipboardList,
    LayoutGrid,
    MessageSquareWarning,
    PanelLeftClose,
    Table2,
    Thermometer,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
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
    const { isMobile, state, open, openMobile, setOpen, setOpenMobile } =
        useSidebar();
    const isCollapsed = !isMobile && state === 'collapsed';
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
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="group-data-[collapsible=icon]:items-center">
                <div className="flex h-14 w-full items-center justify-between gap-2 px-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <Link
                        href={dashboard()}
                        prefetch
                        className="flex min-w-0 items-center"
                    >
                        <AppLogo />
                    </Link>
                    {!isCollapsed && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Toggle sidebar"
                            className="h-10 w-10 shrink-0 rounded-xl border border-sidebar-border bg-white text-sidebar-foreground shadow-sm transition-all duration-200 ease-in-out hover:bg-sidebar-accent group-data-[collapsible=icon]:hidden"
                            onClick={() =>
                                isMobile
                                    ? setOpenMobile(!openMobile)
                                    : setOpen(!open)
                            }
                        >
                            <PanelLeftClose className="size-5" />
                        </Button>
                    )}
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
