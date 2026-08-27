import { Head } from '@inertiajs/react';
import UserForm from '@/components/users/user-form';
import Heading from '@/components/heading';
import { create, index, store } from '@/routes/users';
import type { OfficeResource, RoleResource } from '@/types';

type CreateUserProps = {
    roles: RoleResource[];
    offices: OfficeResource[];
};

export default function CreateUser({ roles, offices }: CreateUserProps) {
    return (
        <>
            <Head title="Create User" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Create User"
                    description="Add a new employee account to the system"
                />

                <UserForm
                    mode="create"
                    roles={roles}
                    offices={offices}
                    action={store().url}
                    method="post"
                />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
        {
            title: 'Create User',
            href: create(),
        },
    ],
};
