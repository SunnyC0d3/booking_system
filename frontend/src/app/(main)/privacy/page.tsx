'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import {
    Shield,
    Lock,
    Eye,
    Database,
    Settings,
    AlertCircle,
    Clock,
    Mail,
    Phone,
    MapPin,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import { MainLayout } from '@/components/layout';
import Link from 'next/link';

const privacyData = {
    lastUpdated: 'January 15, 2025',
    effectiveDate: 'January 15, 2025',
    sections: [
        {
            icon: Database,
            title: 'Information We Collect',
            content: [
                'Personal Information: When you create an account, place an order, or contact us, we may collect personal information such as your name, email address, phone number, billing and shipping addresses.',
                'Payment Information: We collect payment details necessary to process your orders, but do not store complete credit card information on our servers.',
                'Design Files: We temporarily store design files and artwork you upload for the purpose of fulfilling your orders.',
                'Communication Records: We maintain records of your communications with our customer service team to provide better support.',
                'Website Usage Data: We collect information about how you interact with our website, including pages visited, time spent, and referral sources.',
                'Device Information: We may collect information about your device, browser type, IP address, and operating system for security and optimization purposes.',
            ],
        },
        {
            icon: Eye,
            title: 'How We Use Your Information',
            content: [
                'Order Processing: To process and fulfill your orders, including production, shipping, and customer service.',
                'Account Management: To create and maintain your account, process payments, and provide order history.',
                'Communication: To send order confirmations, shipping updates, and respond to your inquiries.',
                'Service Improvement: To analyze website usage and customer feedback to improve our services and user experience.',
                'Marketing: To send promotional emails and special offers (only with your consent, and you can opt out at any time).',
                'Legal Compliance: To comply with applicable laws, regulations, and legal processes.',
                'Security: To protect against fraud, unauthorized access, and other security threats.',
            ],
        },
        {
            icon: Settings,
            title: 'Information Sharing and Disclosure',
            content: [
                'Service Providers: We may share information with trusted third-party service providers who assist us in operating our business, such as payment processors, shipping companies, and cloud storage providers.',
                'Business Transfers: In the event of a merger, acquisition, or sale of assets, customer information may be transferred as part of the transaction.',
                'Legal Requirements: We may disclose information when required by law, court order, or government regulation.',
                'Consent: We may share information with your explicit consent or at your direction.',
                'Aggregated Data: We may share aggregated, non-personally identifiable information for business analysis and research purposes.',
                'No Sale of Personal Data: We do not sell, rent, or lease your personal information to third parties for their marketing purposes.',
            ],
        },
        {
            icon: Lock,
            title: 'Data Security',
            content: [
                'Encryption: We use industry-standard SSL encryption to protect data transmitted between your browser and our servers.',
                'Secure Storage: Personal information is stored on secure servers with appropriate access controls and monitoring.',
                'Payment Security: Payment information is processed through PCI DSS compliant payment processors and is not stored on our servers.',
                'Access Controls: We limit access to personal information to employees and contractors who need it to perform their duties.',
                'Regular Updates: We regularly update our security measures and conduct security assessments to identify and address potential vulnerabilities.',
                'Incident Response: We have procedures in place to respond to and notify relevant parties of any security incidents affecting personal data.',
            ],
        },
        {
            icon: Settings,
            title: 'Your Rights and Choices',
            content: [
                'Access: You have the right to access the personal information we hold about you.',
                'Correction: You can request correction of inaccurate or incomplete personal information.',
                'Deletion: You may request deletion of your personal information, subject to certain legal and business requirements.',
                'Portability: You have the right to receive your personal information in a structured, machine-readable format.',
                'Opt-out: You can opt out of marketing communications at any time by clicking the unsubscribe link in emails or contacting us.',
                'Account Deactivation: You can deactivate your account at any time through your account settings or by contacting us.',
                'Cookie Preferences: You can manage cookie preferences through your browser settings.',
            ],
        },
        {
            icon: Database,
            title: 'Data Retention',
            content: [
                'Account Information: We retain account information for as long as your account is active or as needed to provide services.',
                'Order History: Order information is retained for business and legal purposes, typically for 7 years after the order date.',
                'Design Files: Uploaded design files are typically deleted 90 days after order completion unless you request longer retention.',
                'Communication Records: Customer service communications are retained for up to 3 years for quality assurance and training purposes.',
                'Website Analytics: Website usage data is typically retained for 2 years for business analysis purposes.',
                'Legal Requirements: Some information may be retained longer if required by law or for legitimate business purposes.',
            ],
        },
        {
            icon: Eye,
            title: 'Cookies and Tracking',
            content: [
                'Essential Cookies: We use cookies necessary for website functionality, such as maintaining your shopping cart and login session.',
                'Analytics Cookies: We use analytics tools to understand website usage and improve user experience.',
                'Marketing Cookies: With your consent, we may use cookies for targeted advertising and marketing purposes.',
                'Third-party Cookies: Some third-party services we use may set their own cookies, subject to their privacy policies.',
                'Cookie Management: You can control cookie settings through your browser, though disabling certain cookies may affect website functionality.',
                'Do Not Track: We respect Do Not Track browser settings where technically feasible.',
            ],
        },
        {
            icon: MapPin,
            title: 'International Data Transfers',
            content: [
                'Data Location: Your personal information may be stored and processed in the United Kingdom and other countries where we or our service providers operate.',
                'Adequate Protection: When transferring data internationally, we ensure appropriate safeguards are in place to protect your information.',
                'Legal Basis: International transfers are made based on adequacy decisions, standard contractual clauses, or other approved transfer mechanisms.',
                'Third-party Services: Some of our service providers may be located outside the UK/EU, and we ensure they provide adequate data protection.',
            ],
        },
        {
            icon: Settings,
            title: 'Children\'s Privacy',
            content: [
                'Age Restriction: Our services are not intended for children under 13 years of age, and we do not knowingly collect personal information from children under 13.',
                'Parental Consent: If we become aware that we have collected personal information from a child under 13 without parental consent, we will take steps to delete such information.',
                'Teen Users: Users between 13 and 18 should have parental supervision when using our services.',
                'Family Orders: Parents or guardians may place orders on behalf of minors for family events or projects.',
            ],
        },
        {
            icon: AlertCircle,
            title: 'Changes to This Privacy Policy',
            content: [
                'Policy Updates: We may update this privacy policy from time to time to reflect changes in our practices or legal requirements.',
                'Notification: We will notify you of any material changes by posting the updated policy on our website and, where appropriate, by email.',
                'Effective Date: Changes become effective on the date specified in the updated policy.',
                'Review Reminder: We encourage you to review this privacy policy periodically to stay informed about how we protect your information.',
                'Continued Use: Your continued use of our services after policy changes constitutes acceptance of the updated policy.',
            ],
        },
    ],
};

const contactMethods = [
    {
        icon: Mail,
        title: 'Email',
        value: 'privacy@creativebusiness.com',
        description: 'For privacy-related inquiries',
    },
    {
        icon: Phone,
        title: 'Phone',
        value: '+44 20 7123 4567',
        description: 'Monday-Friday, 9AM-6PM GMT',
    },
    {
        icon: MapPin,
        title: 'Mail',
        value: '123 Creative Street, London, UK EC1A 1AA',
        description: 'Data Protection Officer',
    },
];

export default function PrivacyPage() {
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
                        <div className="flex justify-center mb-6">
                            <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center">
                                <Shield className="h-8 w-8 text-primary" />
                            </div>
                        </div>
                        <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6">
                            Privacy Policy
                        </h1>
                        <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                            Your privacy is important to us. This policy explains how we collect,
                            use, and protect your personal information when you use our services.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center text-sm text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Last Updated: {privacyData.lastUpdated}
                            </div>
                            <div className="flex items-center gap-2">
                                <Shield className="h-4 w-4" />
                                Effective Date: {privacyData.effectiveDate}
                            </div>
                        </div>
                    </motion.div>
                </div>
            </section>

            {/* Privacy Commitment */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <div className="max-w-4xl mx-auto">
                        {/* Commitment Statement */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mb-12"
                        >
                            <Card className="border-primary/20 bg-primary/5">
                                <CardContent className="p-6">
                                    <div className="flex items-start gap-3">
                                        <Shield className="h-6 w-6 text-primary flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h3 className="font-semibold text-foreground mb-2">Our Commitment to Your Privacy</h3>
                                            <p className="text-muted-foreground">
                                                Creative Business is committed to protecting your privacy and ensuring the security
                                                of your personal information. We only collect information necessary to provide our
                                                services and never sell your personal data to third parties.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Privacy Sections */}
                        <div className="space-y-8">
                            {privacyData.sections.map((section, index) => (
                                <motion.div
                                    key={index}
                                    initial={{ opacity: 0, y: 20 }}
                                    whileInView={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: index * 0.1 }}
                                    viewport={{ once: true }}
                                >
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-xl flex items-center gap-3">
                                                <div className="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                                                    <section.icon className="h-4 w-4 text-primary" />
                                                </div>
                                                {index + 1}. {section.title}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {section.content.map((paragraph, pIndex) => (
                                                    <p key={pIndex} className="text-muted-foreground leading-relaxed">
                                                        {paragraph}
                                                    </p>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            ))}
                        </div>

                        {/* Your Rights Summary */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-12"
                        >
                            <Card className="border-primary/20 bg-primary/5">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="h-5 w-5 text-primary" />
                                        Quick Summary: Your Privacy Rights
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <h4 className="font-medium">You Have the Right To:</h4>
                                            <ul className="text-sm text-muted-foreground space-y-1">
                                                <li>• Access your personal information</li>
                                                <li>• Correct inaccurate information</li>
                                                <li>• Request deletion of your data</li>
                                                <li>• Opt out of marketing communications</li>
                                            </ul>
                                        </div>
                                        <div className="space-y-2">
                                            <h4 className="font-medium">We Promise To:</h4>
                                            <ul className="text-sm text-muted-foreground space-y-1">
                                                <li>• Keep your information secure</li>
                                                <li>• Never sell your personal data</li>
                                                <li>• Use data only for stated purposes</li>
                                                <li>• Respond to your requests promptly</li>
                                            </ul>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Contact Information */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-12"
                        >
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-center mb-2">Privacy Questions or Concerns?</CardTitle>
                                    <p className="text-muted-foreground text-center">
                                        Contact our Data Protection Team using any of the methods below:
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid md:grid-cols-3 gap-6">
                                        {contactMethods.map((method, index) => (
                                            <div key={index} className="text-center">
                                                <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-3">
                                                    <method.icon className="h-6 w-6 text-primary" />
                                                </div>
                                                <h4 className="font-medium mb-1">{method.title}</h4>
                                                <p className="text-sm font-medium text-foreground mb-1">{method.value}</p>
                                                <p className="text-xs text-muted-foreground">{method.description}</p>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* GDPR/Data Protection Notice */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-8"
                        >
                            <Card className="border-success/20 bg-success/5">
                                <CardContent className="p-6">
                                    <div className="flex items-start gap-3">
                                        <Lock className="h-6 w-6 text-success flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h3 className="font-semibold text-foreground mb-2">GDPR & UK Data Protection Compliance</h3>
                                            <p className="text-muted-foreground text-sm">
                                                Creative Business fully complies with the UK General Data Protection Regulation (UK GDPR)
                                                and the Data Protection Act 2018. We are registered with the Information Commissioner's Office (ICO)
                                                and follow all applicable data protection laws and regulations.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Quick Actions */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-8 text-center"
                        >
                            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                <Button asChild>
                                    <Link href="/contact">
                                        <Mail className="mr-2 h-4 w-4" />
                                        Contact Privacy Team
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/terms">
                                        Terms of Service
                                    </Link>
                                </Button>
                            </div>
                        </motion.div>

                        {/* Quick Links */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-8 text-center"
                        >
                            <div className="flex flex-col sm:flex-row gap-4 justify-center text-sm">
                                <a
                                    href="/terms"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Terms of Service
                                </a>
                                <span className="hidden sm:inline text-muted-foreground">|</span>
                                <a
                                    href="/contact"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Contact Us
                                </a>
                                <span className="hidden sm:inline text-muted-foreground">|</span>
                                <a
                                    href="/help"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Help Center
                                </a>
                                <span className="hidden sm:inline text-muted-foreground">|</span>
                                <a
                                    href="https://ico.org.uk"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    ICO Website
                                </a>
                            </div>
                        </motion.div>
                    </div>
                </div>
            </section>
        </MainLayout>
    );
}