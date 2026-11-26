export type UserRole = 'tech_admin' | 'admin' | 'staff';

export type User = {
    id: number;
    name: string;
    username?: string | null;
    email: string | null;
    role: UserRole;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
