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
    Star,
    ArrowRight,
    Check,
    Mail,
    Phone,
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

// Statistics
const stats = [
    { number: '10,000+', label: 'Happy Customers' },
    { number: '50,000+', label: 'Products Created' },
    { number: '15+', label: 'Years Experience' },
    { number: '99.8%', label: 'Satisfaction Rate' },
];

// Services offered
const services = [
    'Custom Labels & Stickers',
    'Wedding & Event Invitations',
    'Business Cards & Stationery',
    'Gift Tags & Packaging',
    'Greeting Cards',
    'Flower Stand Creation',
    'Rush Order Service',
    'Design Consultation',
];

export default function AboutPage() {
    return (
        <MainLayout>
            {/* Hero Section */}
            <section className="relative py-20 lg:py-32 bg-gradient-creative">
                <div className="container mx-auto px-4">
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                            className="space-y-6"
                        >
                            <h1 className="text-4xl lg:text-6xl font-bold text-foreground">
                                Crafting Beautiful{' '}
                                <span className="text-gradient">Creative Solutions</span>
                            </h1>
                            <p className="text-xl text-muted-foreground leading-relaxed">
                                For over 15 years, we've been helping individuals and businesses
                                bring their creative visions to life through premium printing and design services.
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
                                <div className="text-muted-foreground">
                                    {stat.label}
                                </div>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Our Story Section */}
            <section className="py-20 bg-muted/20">
                <div className="container mx-auto px-4">
                    <div className="grid lg:grid-cols-2 gap-16 items-center">
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                        >
                            <h2 className="text-3xl lg:text-4xl font-bold mb-6">Our Story</h2>
                            <div className="space-y-4 text-muted-foreground">
                                <p>
                                    Creative Business began in 2009 when founder Sarah Johnson realized
                                    there was a gap in the market for truly personalized, high-quality
                                    printing services that combined creative design with exceptional craftsmanship.
                                </p>
                                <p>
                                    Starting from a small home studio, we've grown into a full-service
                                    creative business while maintaining our commitment to personal attention
                                    and quality that our customers have come to expect.
                                </p>
                                <p>
                                    Today, we're proud to serve thousands of customers worldwide, from
                                    couples planning their dream wedding to businesses looking to make
                                    a lasting impression with their brand materials.
                                </p>
                            </div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            viewport={{ once: true }}
                            className="relative"
                        >
                            <img
                                src="/images/about/our-story.jpg"
                                alt="Our creative journey"
                                className="rounded-2xl w-full h-[400px] object-cover"
                            />
                        </motion.div>
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
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Our Values</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            These core principles guide everything we do and help us deliver
                            exceptional results for our clients.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                        {values.map((value, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="h-full text-center hover:shadow-lg transition-shadow">
                                    <CardHeader>
                                        <div className="mx-auto w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                            <value.icon className="h-6 w-6 text-primary" />
                                        </div>
                                        <CardTitle className="text-xl">{value.title}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">{value.description}</p>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Team Section */}
            <section className="py-20 bg-muted/20">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Meet Our Team</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            The creative minds behind your beautiful projects, dedicated to
                            bringing your vision to life.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {teamMembers.map((member, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="text-center hover:shadow-lg transition-shadow">
                                    <CardContent className="pt-6">
                                        <div className="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden bg-muted">
                                            <img
                                                src={member.image}
                                                alt={member.name}
                                                className="w-full h-full object-cover"
                                                onError={(e) => {
                                                    e.currentTarget.src = `/images/team/placeholder-${index + 1}.jpg`;
                                                }}
                                            />
                                        </div>
                                        <h3 className="text-xl font-bold mb-2">{member.name}</h3>
                                        <p className="text-primary font-medium mb-3">{member.role}</p>
                                        <p className="text-muted-foreground text-sm">{member.bio}</p>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Services Overview */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <div className="grid lg:grid-cols-2 gap-16 items-center">
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                        >
                            <h2 className="text-3xl lg:text-4xl font-bold mb-6">What We Do</h2>
                            <p className="text-xl text-muted-foreground mb-8">
                                From custom labels to wedding invitations, we offer a complete
                                range of creative printing and design services.
                            </p>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
                                {services.map((service, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <Check className="h-5 w-5 text-primary flex-shrink-0" />
                                        <span className="text-muted-foreground">{service}</span>
                                    </div>
                                ))}
                            </div>

                            <Button size="lg">
                                <Link href="/services">
                                    <span className="flex items-center">
                                        Explore Our Services
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </span>
                                </Link>
                            </Button>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            viewport={{ once: true }}
                            className="relative"
                        >
                            <img
                                src="/images/about/services-overview.jpg"
                                alt="Our creative services"
                                className="rounded-2xl w-full h-[500px] object-cover"
                            />
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-20 bg-gradient-creative">
                <div className="container mx-auto px-4 text-center">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="max-w-2xl mx-auto"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-6">
                            Ready to Bring Your Vision to Life?
                        </h2>
                        <p className="text-xl text-muted-foreground mb-8">
                            Let's work together to create something beautiful. Get in touch today
                            to discuss your next project.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button size="lg">
                                <Link href="/contact">
                                    <Mail className="mr-2 h-4 w-4" />
                                    Get In Touch
                                </Link>
                            </Button>
                            <Button variant="outline" size="lg">
                                <Link href="tel:+1555123456">
                                    <span className="flex items-center">
                                        <Phone className="mr-2 h-4 w-4" />
                                        Call Us Today
                                    </span>
                                </Link>
                            </Button>
                        </div>
                    </motion.div>
                </div>
            </section>
        </MainLayout>
    );
}