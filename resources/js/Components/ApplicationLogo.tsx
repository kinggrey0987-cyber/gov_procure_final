import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo({ className = 'h-9 w-9 rounded-full object-cover', ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/logo.jpg"
            alt="GovSupply Connect Logo"
            className={className}
            {...props}
        />
    );
}





