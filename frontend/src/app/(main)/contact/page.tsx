'use client'

import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Heart,
    Users,
    Award,
    Lightbulb,
    Palette,
    ArrowRight,
} from 'lucide-react';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import { MainLayout } from '@/components/layout';

// Team member data
const teamMembers = [
    {
        name: 'Sarah Johnson',
        role: 'Founder & Creative Director',
        image: '/images/team/sarah.jpg',
        bio: 'With over 15 years in graphic design and a passion for beautiful print work, Sarah founded Creative Business to help others bring their visions to life.',
    },
    {
        name: 'Michael Chen',
        role: 'Production Manager',
        image: '/images/team/michael.jpg',
        bio: 'Michael ensures every order meets our high quality standards. His attention to detail and process optimization keeps our production running smoothly.',
    },
    {
        name: 'Emma Rodriguez',
        role: 'Customer Experience Lead',
        image: '/images/team/emma.jpg',
        bio: 'Emma works directly with clients to understand their needs and guide them through the design process, ensuring exceptional results every time.',
    },
];

// Company values
const values = [
    {
        icon: Palette,
        title: 'Creative Excellence',
        description: 'We believe every project deserves thoughtful design and meticulous attention to detail.',
    },
    {
        icon: Users,
        title: 'Personal Service',
        description: 'Each client receives personalized attention and support throughout their creative journey.',
    },
    {
        icon: Award,
        title: 'Quality Craftsmanship',
        description: 'We use premium materials and proven techniques to ensure lasting, beautiful results.',
    },
    {
        icon: Lightbulb,
        title: 'Innovation',
        description: 'We stay current with design trends and printing technology to offer the best solutions.',
    },
];

// Company stats
const stats = [
    { number: '10,000+', label: 'Happy Customers' },
    { number: '50,000+', label: 'Orders Completed' },
    { number: '15+', label: 'Years Experience' },
    { number: '24/7', label: 'Customer Support' },
];

// Process steps
const processSteps = [
    {
        step: '01',
        title: 'Consultation',
        description: 'We discuss your vision, requirements, and timeline to understand your project goals.',
        icon: MessageCircle,
    },
    {
        step: '02',
        title: 'Design',
        description: 'Our creative team develops custom designs based on your specifications and brand.',
        icon: Palette,
    },
    {
        step: '03',
        title: 'Review',
        description: 'We present the designs for your feedback and make revisions until it\'s perfect.',
        icon: Eye,
    },
    {
        step: '04',
        title: 'Production',
        description: 'Your approved designs are carefully printed using premium materials and techniques.',
        icon: Settings,
    },
    {
        step: '05',
        title: 'Delivery',
        description: 'We package and ship your order with care, ensuring safe arrival at your doorstep.',
        icon: Package,
    },
];

export default function AboutPage() {
    return (
        <MainLayout>
            {/* Hero Section */}
            <section className="relative py-20 lg:py-32 bg-gradient-creative overflow-hidden">
                <div className="container mx-auto px-4">
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                        >
                            <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6">
                                Creating Beautiful
                                <span className="text-primary block">Memories Together</span>
                            </h1>
                            <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                                We're passionate about helping you create stunning printed materials
                                that capture your special moments and express your unique style.
                                From wedding invitations to custom labels, every project tells a story.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-4">
                                <Button size="lg">
                                    <Link href="/contact">
                                        <span className="flex items-center">
                                            Get Started Today
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </span>
                                    </Link>
                                </Button>
                                <Button variant="outline" size="lg">
                                    <Link href="/services">
                                        <span className="flex items-center">
                                            Our Services
                                        </span>
                                    </Link>
                                </Button>
                            </div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 20 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            className="relative"
                        >
                            <div className="relative rounded-2xl overflow-hidden">
                                <img
                                    src="/images/about/creative-workspace.jpg"
                                    alt="Creative workspace with design materials"
                                    className="w-full h-[500px] object-cover"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent" />
                            </div>
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* Stats Section */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-8">
                        {stats.map((stat, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                                className="text-center"
                            >
                                <div className="text-3xl lg:text-4xl font-bold text-primary mb-2">
                                    {stat.number}
                                </div>
                                <div className="text-sm font-medium text-muted-foreground">
                                    {stat.label}
                                </div>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Our Story */}
            <section className="py-20 bg-muted/30">
                <div className="container mx-auto px-4">
                    <div className="max-w-4xl mx-auto">
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="text-center mb-16"
                        >
                            <h2 className="text-3xl lg:text-4xl font-bold mb-6">Our Story</h2>
                            <p className="text-xl text-muted-foreground leading-relaxed">
                                Founded with a vision to make beautiful, professional printing accessible to everyone,
                                Creative Business has been helping customers bring their ideas to life since 2010.
                            </p>
                        </motion.div>

                        <div className="grid lg:grid-cols-2 gap-12 items-center">
                            <motion.div
                                initial={{ opacity: 0, x: -20 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                transition={{ duration: 0.6 }}
                                viewport={{ once: true }}
                            >
                                <div className="space-y-6">
                                    <p className="text-muted-foreground leading-relaxed">
                                        What started as a small family business with a simple goal - to provide
                                        beautiful, affordable custom printing - has grown into a trusted partner
                                        for thousands of customers celebrating life's special moments.
                                    </p>
                                    <p className="text-muted-foreground leading-relaxed">
                                        From our humble beginnings in a small London workshop to our modern
                                        facility equipped with state-of-the-art printing technology, we've
                                        never lost sight of what matters most: creating products that make
                                        our customers smile.
                                    </p>
                                    <p className="text-muted-foreground leading-relaxed">
                                        Today, we're proud to serve customers worldwide while maintaining
                                        the personal touch and attention to quality that has been our
                                        foundation from day one.
                                    </p>
                                </div>
                            </motion.div>

                            <motion.div
                                initial={{ opacity: 0, x: 20 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                viewport={{ once: true }}
                            >
                                <div className="relative rounded-2xl overflow-hidden">
                                    <img
                                        src="/images/about/founder-story.jpg"
                                        alt="Creative Business founder working on designs"
                                        className="w-full h-[400px] object-cover"
                                    />
                                </div>
                            </motion.div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Values Section */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-6">Our Values</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            These core principles guide everything we do and every interaction we have with our customers.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-2 xl:grid-cols-4 gap-8">
                        {values.map((value, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="h-full text-center hover:shadow-lg transition-all duration-300">
                                    <CardHeader>
                                        <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                            <value.icon className="h-8 w-8 text-primary" />
                                        </div>
                                        <CardTitle className="text-xl">{value.title}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground leading-relaxed">
                                            {value.description}
                                        </p>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Team Section */}
            <section className="py-20 bg-muted/30">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-6">Meet Our Team</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            The passionate people behind Creative Business who make your projects possible.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-3 gap-8">
                        {teamMembers.map((member, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="text-center hover:shadow-lg transition-all duration-300">
                                    <CardContent className="p-6">
                                        <div className="relative w-32 h-32 mx-auto mb-6 rounded-full overflow-hidden">
                                            <img
                                                src={member.image}
                                                alt={member.name}
                                                className="w-full h-full object-cover"
                                            />
                                        </div>
                                        <h3 className="text-xl font-bold mb-2">{member.name}</h3>
                                        <p className="text-primary font-medium mb-4">{member.role}</p>
                                        <p className="text-muted-foreground text-sm leading-relaxed">
                                            {member.bio}
                                        </p>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Call to Action */}
            <section className="py-20 bg-primary text-primary-foreground">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center max-w-3xl mx-auto"
                    >
                        <Heart className="h-16 w-16 mx-auto mb-6 text-primary-foreground/80" />
                        <h2 className="text-3xl lg:text-4xl font-bold mb-6">
                            Ready to Create Something Beautiful?
                        </h2>
                        <p className="text-xl mb-8 text-primary-foreground/90 leading-relaxed">
                            Let's work together to bring your creative vision to life with professional printing
                            that exceeds your expectations.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button size="lg" variant="secondary">
                                <Link href="/contact">
                                    Start Your Project
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline">
                                <Link href="/products">
                                    View Our Work
                                </Link>
                            </Button>
                        </div>
                    </motion.div>
                </div>
            </section>
        </MainLayout>
    );
}