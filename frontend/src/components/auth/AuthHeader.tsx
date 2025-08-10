import Link from 'next/link';
import {
    ArrowLeft, Palette, Heart } from 'lucide-react';

interface AuthHeaderProps
{
showBackButton?: boolean;
backHref?: string;
}

export function AuthHeader({
                               showBackButton = false, backHref = '/' }: AuthHeaderProps) {
    return (
    <header className = "w-full p-6 flex items-center justify-between" >
      <div className = "flex items-center gap-4" >
        {
            showBackButton && (
          <Link
            href ={
            backHref}
            className = "inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
            <ArrowLeft className = "h-4 w-4" />
            Back
          </Link >
        )}
      </div >

      {/* Logo */
      }
      <Link href = "/" className = "flex items-center gap-3 group" >
        <div className = "relative" >
          <div className = "w-10 h-10 bg-primary rounded-xl flex items-center justify-center group-hover:shadow-glow transition-all duration-300" >
            <Palette className = "h-5 w-5 text-primary-foreground" />
          </div >
          <div className = "absolute -top-1 -right-1 w-4 h-4 bg-cream-400 rounded-full flex items-center justify-center" >
            <Heart className = "h-2 w-2 text-white" />
          </div >
        </div >
        <div className = "hidden sm:block" >
          <h1 className = "font-bold text-xl text-foreground group-hover:text-primary transition-colors" >
        Creative Business
    </h1 >
          <p className = "text-xs text-muted-foreground" >
        Labels • Invitations • Stickers
    </p >
        </div >
      </Link >

      <div className = "w-16" />
    </header >
  );
}