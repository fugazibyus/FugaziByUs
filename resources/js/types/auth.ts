export type User = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    /** "First Last", or the email address when no name is set. */
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};
