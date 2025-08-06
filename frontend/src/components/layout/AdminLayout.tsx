'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/cn';

interface QuickStatsProps {
    stats: Array<{
        title: string;
        value: string | number;
        change?: {
            value: number;
            type: 'increase' | 'decrease' | 'neutral';
            period: string;
        };
        icon?: React.ComponentType<{ className?: string }>;
        color?: 'blue' | 'green' | 'yellow' | 'red' | 'purple' | 'indigo';
    }>;
    className?: string;
}

const colorVariants = {
    blue: 'text-blue-600 bg-blue-50 border-blue-200',
    green: 'text-green-600 bg-green-50 border-green-200',
    yellow: 'text-yellow-600 bg-yellow-50 border-yellow-200',
    red: 'text-red-600 bg-red-50 border-red-200',
    purple: 'text-purple-600 bg-purple-50 border-purple-200',
    indigo: 'text-indigo-600 bg-indigo-50 border-indigo-200',
};

export const QuickStats: React.FC<QuickStatsProps> = ({ stats, className }) => {
    return (
        <div className={cn("grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6", className)}>
            {stats.map((stat, index) => {
                const Icon = stat.icon;
                const colorClass = stat.color ? colorVariants[stat.color] : colorVariants.blue;

                return (
                    <motion.div
                        key={stat.title}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.5, delay: index * 0.1 }}
                    >
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    {stat.title}
                                </CardTitle>
                                {Icon && (
                                    <div className={cn("p-2 rounded-lg", colorClass)}>
                                        <Icon className="h-4 w-4" />
                                    </div>
                                )}
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-foreground mb-2">
                                    {stat.value}
                                </div>
                                {stat.change && (
                                    <div className="flex items-center text-sm">
                                        {stat.change.type === 'increase' && (
                                            <TrendingUp className="h-4 w-4 text-green-500 mr-1" />
                                        )}
                                        {stat.change.type === 'decrease' && (
                                            <TrendingDown className="h-4 w-4 text-red-500 mr-1" />
                                        )}
                                        {stat.change.type === 'neutral' && (
                                            <Minus className="h-4 w-4 text-gray-500 mr-1" />
                                        )}
                                        <span
                                            className={cn(
                                                "font-medium",
                                                stat.change.type === 'increase' && "text-green-600",
                                                stat.change.type === 'decrease' && "text-red-600",
                                                stat.change.type === 'neutral' && "text-gray-600"
                                            )}
                                        >
                                            {stat.change.type === 'increase' ? '+' : ''}
                                            {stat.change.value}%
                                        </span>
                                        <span className="text-muted-foreground ml-1">
                                            from {stat.change.period}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                );
            })}
        </div>
    );
};