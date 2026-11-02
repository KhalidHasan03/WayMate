import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useEffect, useRef } from 'react'

export interface MapMarker {
  id: string | number
  lat: number
  lng: number
  label: string
  kind?: 'origin' | 'destination' | 'live'
  accuracyM?: number | null
}

interface MapViewProps {
  route?: [number, number][] | null
  markers?: MapMarker[]
  className?: string
  heightClass?: string
}

const DEFAULT_CENTER: [number, number] = [23.8103, 90.4125]

export function MapView({ route, markers = [], className = '', heightClass = 'h-56' }: MapViewProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const mapRef = useRef<L.Map | null>(null)
  const layerRef = useRef<L.LayerGroup | null>(null)
  const hasFitRef = useRef(false)

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return

    const map = L.map(containerRef.current, {
      zoomControl: true,
      attributionControl: true,
      scrollWheelZoom: false,
    }).setView(DEFAULT_CENTER, 12)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    mapRef.current = map

    return () => {
      map.remove()
      mapRef.current = null
      layerRef.current = null
      hasFitRef.current = false
    }
  }, [])

  useEffect(() => {
    const map = mapRef.current
    if (!map) return

    layerRef.current?.remove()
    const layer = L.layerGroup().addTo(map)
    layerRef.current = layer

    const bounds = L.latLngBounds([])

    if (route && route.length >= 2) {
      const line = L.polyline(
        route.map(([lat, lng]) => [lat, lng] as [number, number]),
        { color: '#0f766e', weight: 5, opacity: 0.85, lineCap: 'round' },
      ).addTo(layer)
      bounds.extend(line.getBounds())
    }

    markers.forEach((marker) => {
      if (!Number.isFinite(marker.lat) || !Number.isFinite(marker.lng)) return

      const live = marker.kind === 'live'
      const color =
        marker.kind === 'origin' ? '#10b981' : marker.kind === 'destination' ? '#111827' : '#2563eb'

      const circle = L.circleMarker([marker.lat, marker.lng], {
        radius: live ? 9 : 7,
        color: '#ffffff',
        weight: 3,
        fillColor: color,
        fillOpacity: 1,
      }).addTo(layer)

      circle.bindTooltip(marker.label, { direction: 'top', offset: [0, -6] })
      bounds.extend([marker.lat, marker.lng])

      if (live && marker.accuracyM && marker.accuracyM > 10) {
        L.circle([marker.lat, marker.lng], {
          radius: Math.min(marker.accuracyM, 500),
          color: '#2563eb',
          weight: 1,
          opacity: 0.25,
          fillColor: '#3b82f6',
          fillOpacity: 0.12,
          interactive: false,
        }).addTo(layer)
      }
    })

    if (bounds.isValid() && !hasFitRef.current) {
      map.fitBounds(bounds.pad(0.18), { maxZoom: 16, animate: false })
      hasFitRef.current = true
    }
  }, [route, markers])

  return (
    <div
      ref={containerRef}
      className={`${heightClass} w-full overflow-hidden rounded-2xl border border-ink-200 bg-ink-100 ${className}`}
      role="img"
      aria-label="Route map"
    />
  )
}
