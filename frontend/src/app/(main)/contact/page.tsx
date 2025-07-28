// frontend/src/app/(main)/contact/page.tsx
import * as React from 'react';
import { Metadata } from 'next';
import Link from 'next/link';
import { motion } from 'framer-motion';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    Mail,
    Phone,
    MapPin,
    Clock,
    MessageCircle,
    Send,
    CheckCircle,
    AlertCircle,
    Facebook,
    Twitter,
    Instagram,
    Linkedin,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
} from '@/components/ui';
import { MainLayout } from '@/components/layout';
import { useNotificationStore } from '@/stores/notificationStore';

export const metadata: Metadata = {
    title: 'Contact Us | Creative Business',
    description: 'Get in touch with our creative team. We\'re here to help bring your design vision to life with professional printing services.',
};

// Contact form validation schema
const contactSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Please enter a valid email address'),
    phone: z.string().optional(),
    subject: z.string().min(3, 'Subject must be at least 3 characters'),
    service: z.string().optional(),
    message: z.string().min(10, 'Message must be at least 10 characters'),
    budget: z.string().optional(),
    timeline: z.string().optional(),
});

type ContactFormData = z.infer<typeof contactSchema>;

// Contact information
const contactInfo = [
    {
        icon: Mail,
        title: 'Email Us',
        primary: 'hello@creativebusiness.com',
        secondary: 'For general inquiries',
        action: 'mailto:hello@creativebusiness.com',
        actionText: 'Send Email',
    },
    {
        icon: Phone,
        title: 'Call Us',
        primary: '+44 20 7123 4567',
        secondary: 'Mon-Fri, 9AM-6PM GMT',
        action: 'tel:+442071234567',
        actionText: 'Call Now',
    },
    {
        icon: MapPin,
        title: 'Visit Us',
        primary: '123 Creative Street',
        secondary: 'London, UK EC1A 1AA',
        action: 'https://maps.google.com/?q=123+Creative+Street+London',
        actionText: 'Get Directions',
    },
    {
        icon: MessageCircle,
        title: 'Live Chat',
        primary: 'Chat with our team',
        secondary: 'Available during business hours',
        action: '#',
        actionText: 'Start Chat',
    },
];

// Business hours
const businessHours = [
    { day: 'Monday - Friday', hours: '9:00 AM - 6:00 PM' },
    { day: 'Saturday', hours: '10:00 AM - 4:00 PM' },
    { day: 'Sunday', hours: 'Closed' },
];

// Service options for form
const serviceOptions = [
    'Custom Labels',
    'Wedding Invitations',
    'Gift Tags',
    'Stickers & Decals',
    'Greeting Cards',
    'Packaging Inserts',
    'Flower Stands',
    'Rush Orders',
    'Design Consultation',
    'Corporate Solutions',
    'Other',
];

// FAQ items
const faqs = [
    {
        question: 'What\'s your typical turnaround time?',
        answer: 'Standard orders typically take 5-7 business days. Rush orders can be completed in 24-48 hours for an additional fee.',
    },
    {
        question: 'Do you offer design services?',
        answer: 'Yes! Our design team can create custom designs or work with your existing artwork. Design consultation is included with most orders.',
    },
    {
        question: 'What file formats do you accept?',
        answer: 'We accept most common formats including PDF, AI, EPS, PNG, and JPG. High-resolution files (300 DPI) work best for printing.',
    },
    {
        question: 'Do you ship internationally?',
        answer: 'Yes, we ship worldwide. International shipping rates and times vary by location. Contact us for specific quotes.',
    },
];

export default function ContactPage() {
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [isSubmitted, setIsSubmitted] = React.useState(false);
    const { addNotification } = useNotificationStore();

    const {
        register,
        handleSubmit,
        formState: { errors },
        reset,
    } = useForm<ContactFormData>({
        resolver: zodResolver(contactSchema),
    });

    const onSubmit = async (data: ContactFormData) => {
        setIsSubmitting(true);

        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 2000));

            // In real implementation, make API call to backend
            console.log('Contact form data:', data);

            setIsSubmitted(true);
            reset();
            addNotification({
                type: 'success',
                title: 'Message Sent!',
                message: 'Thank you for contacting us. We\'ll get back to you within 24 hours.',
            });
        } catch (error) {
            addNotification({
                type: 'error',
                title: 'Error',
                message: 'Failed to send message. Please try again.',
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <MainLayout>
            {/* Hero Section */}
            <section className="relative py-20 lg:py-32 bg-gradient-creative">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        className="text-center max-w-3xl mx-auto"
                    >
                        <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6">
                            Let's Create Something{' '}
                            <span className="text-gradient">Amazing Together</span>
                        </h1>
                        <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                            Ready to bring your creative vision to life? Get in touch with our team
                            and let's discuss your next project.
                        </p>
                        <Badge variant="secondary" className="text-lg px-4 py-2">
                            <Clock className="mr-2 h-4 w-4" />
                            We typically respond within 2 hours
                        </Badge>
                    </motion.div>
                </div>
            </section>

            {/* Contact Methods */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
                        {contactInfo.map((info, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="text-center hover:shadow-lg transition-shadow h-full">
                                    <CardHeader>
                                        <div className="mx-auto w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                            <info.icon className="h-6 w-6 text-primary" />
                                        </div>
                                        <CardTitle className="text-lg">{info.title}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <p className="font-medium text-foreground">{info.primary}</p>
                                            <p className="text-sm text-muted-foreground">{info.secondary}</p>
                                        </div>
                                        <Button variant="outline" size="sm" asChild className="w-full">
                                            <Link href={info.action}>{info.actionText}</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>

                    {/* Main Contact Form */}
                    <div className="grid lg:grid-cols-2 gap-12">
                        {/* Contact Form */}
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                        >
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-2xl flex items-center gap-2">
                                        <MessageCircle className="h-6 w-6 text-primary" />
                                        Send us a Message
                                    </CardTitle>
                                    <p className="text-muted-foreground">
                                        Fill out the form below and we'll get back to you as soon as possible.
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    {isSubmitted ? (
                                        <div className="text-center py-8">
                                            <CheckCircle className="h-16 w-16 text-success mx-auto mb-4" />
                                            <h3 className="text-xl font-bold mb-2">Thank You!</h3>
                                            <p className="text-muted-foreground">
                                                Your message has been sent successfully. We'll be in touch soon!
                                            </p>
                                        </div>
                                    ) : (
                                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                                            {/* Name and Email */}
                                            <div className="grid md:grid-cols-2 gap-4">
                                                <Input
                                                    {...register('name')}
                                                    label="Full Name"
                                                    placeholder="Your full name"
                                                    error={errors.name?.message}
                                                    required
                                                />
                                                <Input
                                                    {...register('email')}
                                                    type="email"
                                                    label="Email Address"
                                                    placeholder="your@email.com"
                                                    error={errors.email?.message}
                                                    required
                                                />
                                            </div>

                                            {/* Phone and Service */}
                                            <div className="grid md:grid-cols-2 gap-4">
                                                <Input
                                                    {...register('phone')}
                                                    type="tel"
                                                    label="Phone Number (Optional)"
                                                    placeholder="+44 20 1234 5678"
                                                    error={errors.phone?.message}
                                                />
                                                <div>
                                                    <label className="block text-sm font-medium mb-2">
                                                        Service Interested In
                                                    </label>
                                                    <select
                                                        {...register('service')}
                                                        className="w-full px-3 py-2 border border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                                                    >
                                                        <option value="">Select a service</option>
                                                        {serviceOptions.map((service) => (
                                                            <option key={service} value={service}>
                                                                {service}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    {errors.service && (
                                                        <p className="text-sm text-destructive mt-1">
                                                            {errors.service.message}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Subject */}
                                            <Input
                                                {...register('subject')}
                                                label="Subject"
                                                placeholder="What's your project about?"
                                                error={errors.subject?.message}
                                                required
                                            />

                                            {/* Budget and Timeline */}
                                            <div className="grid md:grid-cols-2 gap-4">
                                                <div>
                                                    <label className="block text-sm font-medium mb-2">
                                                        Budget Range (Optional)
                                                    </label>
                                                    <select
                                                        {...register('budget')}
                                                        className="w-full px-3 py-2 border border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                                                    >
                                                        <option value="">Select budget range</option>
                                                        <option value="under-100">Under £100</option>
                                                        <option value="100-500">£100 - £500</option>
                                                        <option value="500-1000">£500 - £1,000</option>
                                                        <option value="1000-5000">£1,000 - £5,000</option>
                                                        <option value="5000-plus">£5,000+</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium mb-2">
                                                        Timeline (Optional)
                                                    </label>
                                                    <select
                                                        {...register('timeline')}
                                                        className="w-full px-3 py-2 border border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                                                    >
                                                        <option value="">Select timeline</option>
                                                        <option value="asap">ASAP (Rush)</option>
                                                        <option value="1-week">Within 1 week</option>
                                                        <option value="2-weeks">Within 2 weeks</option>
                                                        <option value="1-month">Within 1 month</option>
                                                        <option value="flexible">Flexible</option>
                                                    </select>
                                                </div>
                                            </div>

                                            {/* Message */}
                                            <div>
                                                <label className="block text-sm font-medium mb-2">
                                                    Message *
                                                </label>
                                                <textarea
                                                    {...register('message')}
                                                    rows={5}
                                                    className="w-full px-3 py-2 border border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                                                    placeholder="Tell us about your project, requirements, or any questions you have..."
                                                />
                                                {errors.message && (
                                                    <p className="text-sm text-destructive mt-1">
                                                        {errors.message.message}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Submit Button */}
                                            <Button
                                                type="submit"
                                                size="lg"
                                                disabled={isSubmitting}
                                                className="w-full"
                                            >
                                                {isSubmitting ? (
                                                    <>
                                                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                        Sending...
                                                    </>
                                                ) : (
                                                    <>
                                                        <Send className="mr-2 h-4 w-4" />
                                                        Send Message
                                                    </>
                                                )}
                                            </Button>
                                        </form>
                                    )}
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Contact Information & Business Hours */}
                        <motion.div
                            initial={{ opacity: 0, x: 20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            viewport={{ once: true }}
                            className="space-y-8"
                        >
                            {/* Business Hours */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="h-5 w-5 text-primary" />
                                        Business Hours
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {businessHours.map((hour, index) => (
                                            <div key={index} className="flex justify-between">
                                                <span className="text-muted-foreground">{hour.day}</span>
                                                <span className="font-medium">{hour.hours}</span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-4 p-3 bg-primary/5 rounded-lg">
                                        <p className="text-sm text-primary">
                                            <AlertCircle className="h-4 w-4 inline mr-1" />
                                            Rush orders and urgent inquiries are handled outside business hours.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Social Media */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Follow Us</CardTitle>
                                    <p className="text-muted-foreground">
                                        Stay connected for design inspiration and updates.
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex gap-4">
                                        {[
                                            { icon: Facebook, href: '#', label: 'Facebook' },
                                            { icon: Instagram, href: '#', label: 'Instagram' },
                                            { icon: Twitter, href: '#', label: 'Twitter' },
                                            { icon: Linkedin, href: '#', label: 'LinkedIn' },
                                        ].map((social) => (
                                            <Button
                                                key={social.label}
                                                variant="outline"
                                                size="sm"
                                                asChild
                                                className="flex-1"
                                            >
                                                <Link href={social.href}>
                                                    <social.icon className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Quick FAQ */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Quick FAQ</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {faqs.map((faq, index) => (
                                            <div key={index}>
                                                <h4 className="font-medium text-sm mb-1">{faq.question}</h4>
                                                <p className="text-xs text-muted-foreground">{faq.answer}</p>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* Map Section */}
            <section className="py-20 bg-muted/20">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-12"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Visit Our Studio</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            Located in the heart of London, our creative studio is open for
                            consultations and project visits.
                        </p>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, scale: 0.95 }}
                        whileInView={{ opacity: 1, scale: 1 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="rounded-2xl overflow-hidden shadow-lg"
                    >
                        {/* Placeholder for Google Maps - replace with actual map integration */}
                        <div className="bg-muted h-96 flex items-center justify-center">
                            <div className="text-center">
                                <MapPin className="h-12 w-12 text-primary mx-auto mb-4" />
                                <p className="text-lg font-medium">Interactive Map</p>
                                <p className="text-muted-foreground">123 Creative Street, London, UK</p>
                                <Button className="mt-4" asChild>
                                    <Link href="https://maps.google.com/?q=123+Creative+Street+London">
                                        Get Directions
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </section>
        </MainLayout>
    );
}