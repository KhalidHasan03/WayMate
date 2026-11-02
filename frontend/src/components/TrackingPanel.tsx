import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { api, errorMessage } from '../lib/api'
import { formatDateTime } from '../lib/format'
import type { Trip, TripEvent, TripTracking } from '../types'
import { MapView, type MapMarker } from './MapView'

interface TrackingPanelProps {
  trip: Trip
  canManage: boolean
}

export function TrackingPanel({ trip, canManage }: TrackingPanelProps) {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(trip.tracking_enabled)
  const [error, setError] = useState<string | null>(null)
  const [geoError, setGeoError] = useState<string | null>(null)
  const geoSupported = typeof navigator !== 'undefined' && 'geolocation' in navigator

  const trackingQuery = useQuery({
    queryKey: ['tracking', trip.id],
    queryFn: async () =>
      (await api.get<{ data: TripTracking }>(`/trips/${trip.id}/tracking`)).data.data,
    enabled: open,
    refetchInterval: open ? 5_000 : false,
  })

  const tracking = trackingQuery.data
  const enabled = tracking?.tracking_enabled ?? trip.tracking_enabled
  const latest = tracking?.locations.at(-1)

  const startMutation = useMutation({
    mutationFn: async () => api.post(`/trips/${trip.id}/tracking/start`),
    onSuccess: () => {
      setError(null)
      setOpen(true)
      queryClient.invalidateQueries({ queryKey: ['tracking', trip.id] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not start live tracking.')),
  })

  const stopMutation = useMutation({
    mutationFn: async () => api.post(`/trips/${trip.id}/tracking/stop`),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['tracking', trip.id] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not stop live tracking.')),
  })

  useEffect(() => {
    if (!open || !enabled || !canManage || !geoSupported) return

    let lastSent = 0
    let cancelled = false

    const watchId = navigator.geolocation.watchPosition(
      (position) => {
        if (cancelled || Date.now() - lastSent < 9_000) return
        lastSent = Date.now()
        setGeoError(null)

        api
          .post(`/trips/${trip.id}/tracking/points`, {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy_m: position.coords.accuracy,
            speed_mps: position.coords.speed ?? undefined,
            heading_deg: position.coords.heading ?? undefined,
          })
          .then(() => queryClient.invalidateQueries({ queryKey: ['tracking', trip.id] }))
          .catch((err: unknown) => setGeoError(errorMessage(err, 'Could not share your location.')))
      },
      (geo) => {
        if (geo.code === geo.PERMISSION_DENIED) {
          setGeoError('Location permission was denied. Enable it in your browser settings.')
        } else {
          setGeoError('Location is unavailable right now.')
        }
      },
      { enableHighAccuracy: true, maximumAge: 8_000, timeout: 20_000 },
    )

    return () => {
      cancelled = true
      navigator.geolocation.clearWatch(watchId)
    }
  }, [canManage, enabled, geoSupported, open, queryClient, trip.id])

  const route = useMemo<[number, number][] | null>(() => {
    const path = trip.offer?.route_path ?? trip.request?.route_path
    if (path && path.length >= 2) return path
    if (!trip.origin || !trip.destination) return null
    return [
      [trip.origin.lat, trip.origin.lng],
      [trip.destination.lat, trip.destination.lng],
    ]
  }, [trip.destination, trip.offer?.route_path, trip.origin, trip.request?.route_path])

  const markers = useMemo<MapMarker[]>(() => {
    const points: MapMarker[] = []

    if (trip.origin) {
      points.push({
        id: 'origin',
        lat: trip.origin.lat,
        lng: trip.origin.lng,
        label: `Pickup · ${trip.origin.label}`,
        kind: 'origin',
      })
    }

    if (trip.destination) {
      points.push({
        id: 'destination',
        lat: trip.destination.lat,
        lng: trip.destination.lng,
        label: `Drop-off · ${trip.destination.label}`,
        kind: 'destination',
      })
    }

    if (latest) {
      points.push({
        id: 'live',
        lat: latest.lat,
        lng: latest.lng,
        label: 'Live location',
        kind: 'live',
        accuracyM: latest.accuracy_m,
      })
    }

    return points
  }, [latest, trip.destination, trip.origin])

  if (!['forming', 'ready', 'in_progress'].includes(trip.status)) return null

  return (
    <div className="mt-3 rounded-xl border border-ink-200 bg-ink-50/70 p-3">
      <div className="flex items-center justify-between gap-2">
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className="text-left text-xs font-bold text-ink-800"
        >
          {enabled ? '● Live trip map' : 'Live trip map'}
          <span className="block text-[11px] font-medium text-ink-500">
            {enabled
              ? latest
                ? `Updated ${formatDateTime(latest.recorded_at)}`
                : 'Waiting for the first location…'
              : canManage
                ? 'Share your live location with the group'
                : 'The driver can share live location'}
          </span>
        </button>
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className="shrink-0 rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-ink-700"
        >
          {open ? 'Hide' : 'View'}
        </button>
      </div>

      {open && (
        <div className="mt-3 space-y-2">
          <MapView route={route} markers={markers} heightClass="h-52" />

          {trackingQuery.isError && (
            <p className="text-[11px] font-medium text-red-700">Could not load live tracking.</p>
          )}

          {canManage && (
            <button
              type="button"
              onClick={() => (enabled ? stopMutation.mutate() : startMutation.mutate())}
              disabled={startMutation.isPending || stopMutation.isPending}
              className={`w-full rounded-xl px-3 py-2 text-xs font-bold disabled:opacity-60 ${
                enabled
                  ? 'border border-red-200 bg-white text-red-600'
                  : 'bg-brand-600 text-white'
              }`}
            >
              {enabled ? 'Stop sharing location' : 'Share live location'}
            </button>
          )}

          {!geoSupported && (
            <p className="text-[11px] font-medium text-amber-700">
              This browser does not support location sharing.
            </p>
          )}
          {geoSupported && geoError && (
            <p className="text-[11px] font-medium text-amber-700">{geoError}</p>
          )}
          {error && <p className="text-[11px] font-medium text-red-700">{error}</p>}
        </div>
      )}
    </div>
  )
}

export function TripTimeline({ tripId }: { tripId: number }) {
  const { data, isLoading } = useQuery({
    queryKey: ['trip-events', tripId],
    queryFn: async () =>
      (await api.get<{ data: TripEvent[] }>(`/trips/${tripId}/events`)).data.data,
    staleTime: 30_000,
  })

  const events = data ?? []

  return (
    <div className="mt-3 rounded-xl border border-ink-200 bg-white p-3">
      <p className="text-[11px] font-bold uppercase tracking-wide text-ink-400">Trip timeline</p>
      {isLoading && <p className="mt-2 text-[11px] text-ink-400">Loading timeline…</p>}
      {!isLoading && events.length === 0 && (
        <p className="mt-2 text-[11px] text-ink-400">No activity recorded yet.</p>
      )}
      <ol className="mt-2 space-y-2">
        {events.slice(-6).map((event) => (
          <li key={event.id} className="flex items-start gap-2">
            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
            <div className="min-w-0">
              <p className="text-[11px] font-semibold capitalize text-ink-700">
                {event.event.replaceAll('_', ' ')}
              </p>
              <p className="text-[10px] text-ink-400">
                {formatDateTime(event.created_at)}
                {event.actor ? ` · ${event.actor.name}` : ''}
              </p>
            </div>
          </li>
        ))}
      </ol>
    </div>
  )
}
