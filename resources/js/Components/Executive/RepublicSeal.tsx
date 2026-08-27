import React, { SVGAttributes } from 'react';

export default function RepublicSeal(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 200 200"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            {/* Outer rings */}
            <circle cx="100" cy="100" r="96" fill="#0B2545" stroke="#D4AF37" strokeWidth="4" />
            <circle cx="100" cy="100" r="90" fill="#FFFFFF" stroke="#D4AF37" strokeWidth="2" strokeDasharray="4 2" />
            
            {/* Inner Background circle */}
            <circle cx="100" cy="100" r="82" fill="#F8FAFC" stroke="#0B2545" strokeWidth="1.5" />

            {/* Top Stars Arc (Luzon, Visayas, Mindanao) */}
            <g fill="#D4AF37">
                {/* Center Star */}
                <polygon points="100,32 103,40 111,40 105,45 107,53 100,48 93,53 95,45 89,40 97,40" />
                {/* Left Star */}
                <polygon points="62,48 65,56 73,56 67,61 69,69 62,64 55,69 57,61 51,56 59,56" />
                {/* Right Star */}
                <polygon points="138,48 141,56 149,56 143,61 145,69 138,64 131,69 133,61 127,56 135,56" />
            </g>

            {/* Central Shield */}
            <g>
                {/* Shield Outline and Base */}
                <path
                    d="M60 70 L140 70 L140 115 C140 145 100 162 100 162 C100 162 60 145 60 115 Z"
                    fill="#FFFFFF"
                    stroke="#0F172A"
                    strokeWidth="2.5"
                />

                {/* Left half: Blue (Azure) field */}
                <path
                    d="M60 70 L100 70 L100 162 C100 162 60 145 60 115 Z"
                    fill="#0038A8"
                />

                {/* Right half: Red (Gules) field */}
                <path
                    d="M100 70 L140 70 L140 115 C140 145 100 162 100 162 Z"
                    fill="#CE1126"
                />

                {/* Top White Field with Golden Sun */}
                <path
                    d="M60 70 L140 70 L140 98 L60 98 Z"
                    fill="#FFFFFF"
                    stroke="#0F172A"
                    strokeWidth="1"
                />

                {/* Golden Sun with 8 rays */}
                <circle cx="100" cy="84" r="7" fill="#FCD116" stroke="#D4AF37" strokeWidth="1" />
                <g stroke="#FCD116" strokeWidth="2" strokeLinecap="round">
                    <line x1="100" y1="71" x2="100" y2="75" />
                    <line x1="100" y1="93" x2="100" y2="97" />
                    <line x1="87" y1="84" x2="91" y2="84" />
                    <line x1="109" y1="84" x2="113" y2="84" />
                    <line x1="91" y1="75" x2="94" y2="78" />
                    <line x1="106" y1="90" x2="109" y2="93" />
                    <line x1="91" y1="93" x2="94" y2="90" />
                    <line x1="106" y1="78" x2="109" y2="75" />
                </g>

                {/* Center Symbol / Eagle / Lion silhouette hint */}
                <path
                    d="M85 125 C85 110 100 105 100 105 C100 105 115 110 115 125 C115 140 100 148 100 148 C100 148 85 140 85 125 Z"
                    fill="#FCD116"
                    stroke="#D4AF37"
                    strokeWidth="1.5"
                />
            </g>

            {/* Bottom Official Text Banner */}
            <path
                d="M45 168 Q100 188 155 168 L150 178 Q100 198 50 178 Z"
                fill="#D4AF37"
                stroke="#0F172A"
                strokeWidth="1"
            />
            <text
                x="100"
                y="177"
                fontSize="6"
                fontWeight="bold"
                fill="#0B2545"
                textAnchor="middle"
                letterSpacing="1"
            >
                REPUBLIKA NG PILIPINAS
            </text>
        </svg>
    );
}
