import Link from 'next/link';
import { Heart } from 'lucide-react';

export function AuthFooter() {
    return (
        <footer className="w-full p-6 text-center">
            <div className="max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="flex items-center gap-6 text-sm text-muted-foreground">
                        <Link href="/privacy" className="hover:text-foreground transition-colors">
                            Privacy Policy
                        </Link>
                        <Link href="/terms" className="hover:text-foreground transition-colors">
                            Terms of Service
                        </Link>
                        <Link href="/contact" className="hover:text-foreground transition-colors">
                            Contact Us
                        </Link>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        © 2024 Creative Business. Made with{' '}
                        <Heart className="inline h-4 w-4 text-primary" /> for creators.
                    </p>
                </div>
            </div>
        </footer>
    );
}