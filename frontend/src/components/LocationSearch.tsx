import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { api } from '../lib/api'
import type { GeoPlace } from '../types'

interface LocationSearchProps {
  label: string
  placeholder?: string
  value: GeoPlace | null
  onChange: (place: GeoPlace | null) => void
  tone?: 'origin' | 'destination'
}

export function LocationSearch({
  label,
  placeholder,
  value,
  onChange,
  tone = 'origin',
}: LocationSearchProps) {
  const [query, setQuery] = useState(value?.label ?? '')
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)
  const debounced = useDebouncedValue(query, 350)

  useEffect(() => {
    const handler = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const shouldSearch =
    debounced.trim().length >= 2 && debounced.trim() !== value?.label

  const { data, isFetching } = useQuery({
    queryKey: ['geo-search', debounced],
    queryFn: async () =>
      (await api.get<{ data: GeoPlace[] }>('/geo/search', { params: { q: debounced } })).data.data,
    enabled: shouldSearch && open,
    staleTime: 5 * 60 * 1000,
  })

  const results = data ?? []

  return (
    <div ref={containerRef} className="relative">
      <label className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-700">
        <span
          className={`h-2.5 w-2.5 rounded-full ${
            tone === 'origin' ? 'bg-brand-500' : 'bg-ink-900'
          }`}
        />
        {label}
      </label>
      <input
        value={query}
        onChange={(event) => {
          setQuery(event.target.value)
          setOpen(true)
          if (value) onChange(null)
        }}
        onFocus={() => setOpen(true)}
        placeholder={placeholder ?? 'Search area, road or landmark'}
        className="w-full rounded-2xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
      />

      {open && shouldSearch && (
        <div className="absolute z-30 mt-1.5 w-full overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-xl shadow-ink-900/10">
          {isFetching && results.length === 0 && (
            <p className="px-4 py-3 text-xs text-ink-400">Searching…</p>
          )}
          {!isFetching && results.length === 0 && (
            <p className="px-4 py-3 text-xs text-ink-400">No places found. Try a nearby landmark.</p>
          )}
          {results.map((place) => (
            <button
              key={`${place.label}-${place.lat}-${place.lng}`}
              type="button"
              onClick={() => {
                onChange(place)
                setQuery(place.label)
                setOpen(false)
              }}
              className="flex w-full items-start gap-2.5 border-b border-ink-100 px-4 py-3 text-left last:border-0 hover:bg-brand-50/60"
            >
              <span className="mt-0.5 text-brand-600">📍</span>
              <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-ink-900">
                  {place.label.split(',')[0]}
                </span>
                <span className="block truncate text-xs text-ink-400">{place.label}</span>
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
