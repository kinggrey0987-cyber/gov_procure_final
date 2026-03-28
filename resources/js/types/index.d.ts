export interface User {
    id: number;
    name: string;
    email: string;
    role_code?: string;
    office_id?: number;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        can: {
            admin_tech: boolean;
            office: boolean;
            procurement: boolean;
            inventory: boolean;
            executive: boolean;
        };
    };
};
