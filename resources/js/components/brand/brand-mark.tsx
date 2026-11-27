type BrandMarkProps = {
    compact?: boolean;
    inverse?: boolean;
};

export function BrandMark({
    compact = false,
    inverse = false,
}: BrandMarkProps) {
    return (
        <span className="inline-flex items-center gap-3">
            <img
                alt=""
                aria-hidden="true"
                className={
                    compact ? 'size-9 rounded-xl' : 'size-11 rounded-2xl'
                }
                height={compact ? 36 : 44}
                src="/brand/barber-crm-mark.svg"
                width={compact ? 36 : 44}
            />
            <span className="min-w-0">
                <span
                    className={`block font-semibold tracking-tight ${inverse ? 'text-white' : 'text-foreground'}`}
                >
                    Barber CRM
                </span>
                {!compact && (
                    <span
                        className={`block text-xs ${inverse ? 'text-white/60' : 'text-muted-foreground'}`}
                    >
                        Customer relationships
                    </span>
                )}
            </span>
        </span>
    );
}
