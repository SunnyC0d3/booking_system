'use client'

import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Users,
    Search,
    Filter,
    Download,
    Plus,
    Edit,
    Trash2,
    Mail,
    Phone,
    Calendar,
    Shield,
    User,
    MoreHorizontal,
    Eye,
    Ban,
    UserCheck,
    Crown,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    Avatar,
    AvatarFallback,
    AvatarImage,
    Checkbox,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui';
import { AdminLayout, QuickStats } from '@/components/layout/AdminLayout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { cn } from '@/lib/cn';

// Mock user data - replace with real API
const mockUsers = [
    {
        id: 1,
        name: 'Sarah Johnson',
        email: 'sarah@example.com',
        role: 'customer',
        status: 'active',
        avatar: '',
        phone: '+44 20 7123 4567',
        created_at: '2024-01-15',
        last_login: '2025-01-28T10:30:00Z',
        orders_count: 12,
        total_spent: 1250.50,
        location: 'London, UK',
    },
    {
        id: 2,
        name: 'Michael Chen',
        email: 'michael@creativebiz.com',
        role: 'admin',
        status: 'active',
        avatar: '',
        phone: '+44 20 7123 4568',
        created_at: '2023-12-01',
        last_login: '2025-01-28T09:15:00Z',
        orders_count: 0,
        total_spent: 0,
        location: 'Manchester, UK',
    },
    {
        id: 3,
        name: 'Emily Rodriguez',
        email: 'emily@example.com',
        role: 'customer',
        status: 'active',
        avatar: '',
        phone: '+44 20 7123 4569',
        created_at: '2024-02-20',
        last_login: '2025-01-27T16:45:00Z',
        orders_count: 8,
        total_spent: 650.25,
        location: 'Birmingham, UK',
    },
    {
        id: 4,
        name: 'David Wilson',
        email: 'david@example.com',
        role: 'customer',
        status: 'suspended',
        avatar: '',
        phone: '+44 20 7123 4570',
        created_at: '2024-03-10',
        last_login: '2025-01-25T14:20:00Z',
        orders_count: 3,
        total_spent: 180.75,
        location: 'Liverpool, UK',
    },
    {
        id: 5,
        name: 'Lisa Thompson',
        email: 'lisa@example.com',
        role: 'customer',
        status: 'active',
        avatar: '',
        phone: '+44 20 7123 4571',
        created_at: '2024-01-05',
        last_login: '2025-01-28T08:00:00Z',
        orders_count: 25,
        total_spent: 2150.80,
        location: 'Edinburgh, UK',
    },
];

const userStats = [
    {
        title: 'Total Users',
        value: '1,234',
        change: '+12% from last month',
        trend: 'up' as const,
        icon: Users,
        color: 'bg-blue-500',
    },
    {
        title: 'Active Users',
        value: '1,156',
        change: '+8% from last month',
        trend: 'up' as const,
        icon: UserCheck,
        color: 'bg-green-500',
    },
    {
        title: 'New This Month',
        value: '89',
        change: '+23% from last month',
        trend: 'up' as const,
        icon: Plus,
        color: 'bg-purple-500',
    },
    {
        title: 'Admin Users',
        value: '12',
        change: '+2 from last month',
        trend: 'up' as const,
        icon: Crown,
        color: 'bg-orange-500',
    },
];

const getRoleColor = (role: string) => {
    switch (role) {
        case 'super admin':
            return 'bg-red-100 text-red-800';
        case 'admin':
            return 'bg-purple-100 text-purple-800';
        case 'vendor':
            return 'bg-blue-100 text-blue-800';
        case 'customer':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'suspended':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatLastLogin = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));

    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d ago`;
    return date.toLocaleDateString();
};

export default function UsersManagementPage() {
    const [users, setUsers] = React.useState(mockUsers);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [roleFilter, setRoleFilter] = React.useState('all');
    const [statusFilter, setStatusFilter] = React.useState('all');
    const [selectedUsers, setSelectedUsers] = React.useState<number[]>([]);
    const [showUserDialog, setShowUserDialog] = React.useState(false);

    // Filter users based on search and filters
    const filteredUsers = users.filter(user => {
        const matchesSearch = user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            user.email.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesRole = roleFilter === 'all' || user.role === roleFilter;
        const matchesStatus = statusFilter === 'all' || user.status === statusFilter;

        return matchesSearch && matchesRole && matchesStatus;
    });

    const handleSelectAll = () => {
        if (selectedUsers.length === filteredUsers.length) {
            setSelectedUsers([]);
        } else {
            setSelectedUsers(filteredUsers.map(user => user.id));
        }
    };

    const handleSelectUser = (userId: number) => {
        setSelectedUsers(prev =>
            prev.includes(userId)
                ? prev.filter(id => id !== userId)
                : [...prev, userId]
        );
    };

    const handleBulkAction = (action: string) => {
        console.log(`Bulk ${action} for users:`, selectedUsers);
        // Implement bulk actions
        setSelectedUsers([]);
    };

    const handleUserAction = (userId: number, action: string) => {
        console.log(`${action} user:`, userId);
        // Implement individual user actions
    };

    return (
        <RouteGuard requireAuth requiredRoles={['admin', 'super admin']}>
            <AdminLayout
                title="User Management"
                description="Manage customers, staff, and user accounts across your platform."
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm">
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                        <Dialog open={showUserDialog} onOpenChange={setShowUserDialog}>
                            <DialogTrigger>
                                <Button size="sm">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add User
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>Add New User</DialogTitle>
                                </DialogHeader>
                                <div className="py-4">
                                    <p className="text-muted-foreground">
                                        User creation form would go here...
                                    </p>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                }
            >
                <div className="space-y-8">
                    {/* Stats */}
                    <QuickStats stats={userStats} />

                    {/* Filters and Search */}
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="h-5 w-5 text-primary" />
                                    Users ({filteredUsers.length})
                                </CardTitle>

                                <div className="flex flex-col sm:flex-row gap-4">
                                    {/* Search */}
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                        <Input
                                            placeholder="Search users..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="pl-10 w-full sm:w-64"
                                        />
                                    </div>

                                    {/* Role Filter */}
                                    <Select value={roleFilter} onValueChange={setRoleFilter}>
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue placeholder="All Roles" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Roles</SelectItem>
                                            <SelectItem value="customer">Customer</SelectItem>
                                            <SelectItem value="admin">Admin</SelectItem>
                                            <SelectItem value="vendor">Vendor</SelectItem>
                                            <SelectItem value="super admin">Super Admin</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    {/* Status Filter */}
                                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="suspended">Suspended</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Bulk Actions */}
                            {selectedUsers.length > 0 && (
                                <motion.div
                                    initial={{ opacity: 0, height: 0 }}
                                    animate={{ opacity: 1, height: 'auto' }}
                                    exit={{ opacity: 0, height: 0 }}
                                    className="flex items-center gap-2 p-4 bg-primary/5 border border-primary/20 rounded-lg"
                                >
                                    <span className="text-sm font-medium">
                                        {selectedUsers.length} user(s) selected
                                    </span>
                                    <div className="flex gap-2 ml-4">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('activate')}
                                        >
                                            <UserCheck className="mr-2 h-4 w-4" />
                                            Activate
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('suspend')}
                                        >
                                            <Ban className="mr-2 h-4 w-4" />
                                            Suspend
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('delete')}
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </motion.div>
                            )}
                        </CardHeader>

                        <CardContent className="p-0">
                            {/* Users Table */}
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12">
                                                <Checkbox
                                                    checked={selectedUsers.length === filteredUsers.length && filteredUsers.length > 0}
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </TableHead>
                                            <TableHead>User</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Orders</TableHead>
                                            <TableHead>Total Spent</TableHead>
                                            <TableHead>Last Login</TableHead>
                                            <TableHead className="w-12"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredUsers.map((user, index) => (
                                            <motion.tr
                                                key={user.id}
                                                initial={{ opacity: 0, y: 20 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{ duration: 0.3, delay: index * 0.05 }}
                                                className="hover:bg-gray-50"
                                            >
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selectedUsers.includes(user.id)}
                                                        onCheckedChange={() => handleSelectUser(user.id)}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="w-10 h-10">
                                                            <AvatarImage src={user.avatar} />
                                                            <AvatarFallback>
                                                                <User className="h-5 w-5" />
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <p className="font-medium text-gray-900">
                                                                {user.name}
                                                            </p>
                                                            <p className="text-sm text-gray-500">
                                                                {user.email}
                                                            </p>
                                                            {user.location && (
                                                                <p className="text-xs text-gray-400">
                                                                    {user.location}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getRoleColor(user.role)}>
                                                        {user.role}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getStatusColor(user.status)}>
                                                        {user.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        {user.orders_count}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        £{user.total_spent.toFixed(2)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm text-gray-600">
                                                        {formatLastLogin(user.last_login)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuItem onClick={() => handleUserAction(user.id, 'view')}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleUserAction(user.id, 'edit')}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit User
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleUserAction(user.id, 'orders')}>
                                                                <Package className="mr-2 h-4 w-4" />
                                                                View Orders
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            {user.status === 'active' ? (
                                                                <DropdownMenuItem onClick={() => handleUserAction(user.id, 'suspend')}>
                                                                    <Ban className="mr-2 h-4 w-4" />
                                                                    Suspend User
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem onClick={() => handleUserAction(user.id, 'activate')}>
                                                                    <UserCheck className="mr-2 h-4 w-4" />
                                                                    Activate User
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem
                                                                onClick={() => handleUserAction(user.id, 'delete')}
                                                                className="text-red-600"
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                Delete User
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </motion.tr>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {filteredUsers.length === 0 && (
                                <div className="text-center py-12">
                                    <Users className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        No Users Found
                                    </h3>
                                    <p className="text-gray-500">
                                        {searchQuery || roleFilter !== 'all' || statusFilter !== 'all'
                                            ? 'Try adjusting your search or filters.'
                                            : 'No users have been added yet.'
                                        }
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Pagination would go here */}
                    {filteredUsers.length > 10 && (
                        <div className="flex justify-center">
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" disabled>
                                    Previous
                                </Button>
                                <span className="text-sm text-gray-600 px-4">
                                    Page 1 of 1
                                </span>
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </AdminLayout>
        </RouteGuard>
    );
}