interface LogoProps {
  size?: number
  withWordmark?: boolean
}

export function Logo({ size = 40, withWordmark = false }: LogoProps) {
  return (
    <div className="flex items-center gap-2.5">
      <span
        className="grid place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/25"
        style={{ width: size, height: size }}
      >
        <svg width={size * 0.58} height={size * 0.58} viewBox="0 0 24 24" fill="none" aria-hidden>
          <path
            d="M5 17.5c0-5 3.2-7 7-7s7-2 7-5.5"
            stroke="currentColor"
            strokeWidth="2.4"
            strokeLinecap="round"
          />
          <circle cx="5" cy="18" r="2" fill="currentColor" />
          <circle cx="19" cy="5" r="2" fill="currentColor" />
        </svg>
      </span>
      {withWordmark && (
        <span className="text-xl font-bold tracking-tight text-ink-900">
          Way<span className="text-brand-600">Mate</span>
        </span>
      )}
    </div>
  )
}
