import { Head } from '@inertiajs/react';
import UserForm from '@/components/users/user-form';
import Heading from '@/components/heading';
import { edit, index, update } from '@/routes/users';
import type { OfficeResource, RoleResource, UserResource } from '@/types';

type EditUserProps = {
    user: UserResource;
    roles: RoleResource[];
    offices: OfficeResource[];
};

export default function EditUser({ user, roles, offices }: EditUserProps) {
    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Edit User"
                    description={`Update account details for ${user.name}`}
                />

                <UserForm
                    mode="edit"
                    user={user}
                    roles={roles}
                    offices={offices}
                    action={update({ user: user.id }).url}
                    method="put"
                />
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
        {
            title: 'Edit User',
            href: edit({ user: 0 }),
        },
    ],
};
