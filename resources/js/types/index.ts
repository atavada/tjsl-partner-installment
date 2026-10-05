import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export type Role = 'operator' | 'reconciliation_reviewer' | 'process_owner' | 'auditor' | 'viewer' | 'system_admin';

export const ROLE_LABELS: Record<Role, string> = {
    operator: 'Kasir TJSL',
    reconciliation_reviewer: 'Kepala Sub Divisi',
    process_owner: 'Sekper / Kepala Divisi',
    auditor: 'Viewer',
    viewer: 'Viewer',
    system_admin: 'System Admin',
} as const;

export const getRoleLabel = (role: Role): string => ROLE_LABELS[role] ?? role;

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label?: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
