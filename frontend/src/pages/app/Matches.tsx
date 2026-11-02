import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, errorMessage } from '../../lib/api'
import { durationLabel, formatDateTime, taka } from '../../lib/format'
import type { OfferMatch, RequestMatch, RideRequest, Vehicle } from '../../types'

type Kind = 'request' | 'offer'

export function Matches() {
  const { kind, id } = useParams<{ kind: Kind; id: string }>()
  const isRequest = kind === 'request'
  const queryClient = useQueryClient()
  const [joinedIds, setJoinedIds] = useState<number[]>([])
  const [sentIds, setSentIds] = useState<number[]>([])
  const [actionError, setActionError] = useState<string | null>(null)

  const vehiclesQuery = useQuery({
    queryKey: ['vehicles'],
    queryFn: async () => (await api.get<{ data: Vehicle[] }>('/vehicles')).data.data,
    enabled: !isRequest,
  })

  const activeVehicle = (vehiclesQuery.data ?? []).find((vehicle) => vehicle.status === 'active')

  const requestQuery = useQuery({
    queryKey: ['ride-request', id],
    queryFn: async () =>
      (await api.get<{ data: RideRequest }>(`/ride-requests/${id}`)).data.data,
    enabled: isRequest && Boolean(id),
  })

  const seatsNeeded = requestQuery.data?.seats_needed ?? 1

  const joinOfferMutation = useMutation({
    mutationFn: async ({ offerId, seats }: { offerId: number; seats: number }) =>
      api.post(`/ride-offers/${offerId}/join`, { seats }),
    onSuccess: (_, { offerId }) => {
      setActionError(null)
      setJoinedIds((current) => [...current, offerId])
      queryClient.invalidateQueries({ queryKey: ['joins'] })
    },
    onError: (err) => setActionError(errorMessage(err, 'Could not send the join request.')),
  })

  const joinRequestMutation = useMutation({
    mutationFn: async (requestId: number) =>
      api.post(`/ride-requests/${requestId}/join`, { vehicle_id: activeVehicle?.id }),
    onSuccess: (_, requestId) => {
      setActionError(null)
      setSentIds((current) => [...current, requestId])
      queryClient.invalidateQueries({ queryKey: ['joins'] })
    },
    onError: (err) => setActionError(errorMessage(err, 'Could not offer to drive.')),
  })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['matches', kind, id],
    queryFn: async () => {
      const path = isRequest
        ? `/ride-requests/${id}/matches`
        : `/ride-offers/${id}/matches`
      return (await api.get<{ data: (OfferMatch | RequestMatch)[] }>(path)).data
    },
    enabled: Boolean(id) && (kind === 'request' || kind === 'offer'),
  })

  const rows = data?.data ?? []

  return (
    <div className="animate-rise space-y-4 pb-4">
      <Link to="/app/trips" className="text-xs font-semibold text-brand-700">
        ‹ Back to trips
      </Link>

      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">
          {isRequest ? 'Riders on your route' : 'Passengers along your route'}
        </h1>
        <p className="mt-1 text-sm text-ink-500">
          {isRequest
            ? 'These offers follow the same corridor and fit your time window.'
            : 'These seat requests fit your route and departure window.'}
        </p>
      </div>

      {actionError && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {actionError}
        </p>
      )}

      {isLoading && <p className="py-10 text-center text-sm text-ink-400">Finding matches…</p>}

      {isError && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          Could not load matches. Please try again.
        </p>
      )}

      {!isLoading && !isError && rows.length === 0 && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">
            🔎
          </span>
          <h2 className="mt-4 text-base font-bold text-ink-900">No matches yet</h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            We could not find anyone on this corridor right now. New riders and providers
            appear as they post, so check back closer to departure.
          </p>
        </div>
      )}

      {rows.map((row) => {
        const route = 'offer' in row ? row.offer : row.request
        const title = 'offer' in row ? `${row.offer.provider?.name ?? 'A rider'}'s ride` : `${row.request.origin.label} → ${row.request.destination.label}`

        return (
          <article
            key={'offer' in row ? `offer-${row.offer.id}` : `request-${row.request.id}`}
            className="rounded-2xl border border-ink-200 bg-white p-4"
          >
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate text-sm font-bold text-ink-900">{title}</p>
                <p className="mt-0.5 text-xs text-ink-500">{formatDateTime(route.depart_at)}</p>
              </div>
              <span className="shrink-0 rounded-full bg-brand-600 px-2.5 py-1 text-[11px] font-bold text-white">
                {row.match.score}% match
              </span>
            </div>

            <div className="mt-3 space-y-1.5">
              <div className="flex items-start gap-2">
                <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
                <p className="truncate text-sm font-semibold text-ink-900">{route.origin.label}</p>
              </div>
              <div className="ml-[3px] h-2.5 w-px bg-ink-200" />
              <div className="flex items-start gap-2">
                <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-ink-900" />
                <p className="truncate text-sm font-semibold text-ink-900">{route.destination.label}</p>
              </div>
            </div>

            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
              <span>{durationLabel(route.duration_s)}</span>
              <span>Pickup detour {row.match.pickup_deviation_m} m</span>
              {row.match.dropoff_deviation_m > 0 && (
                <span>Drop-off detour {row.match.dropoff_deviation_m} m</span>
              )}
              {'offer' in row ? (
                <span>
                  {row.offer.seats_available} of {row.offer.seats_total} seat
                  {row.offer.seats_total > 1 ? 's' : ''} free
                </span>
              ) : (
                <span>
                  {row.request.seats_needed} seat{row.request.seats_needed > 1 ? 's' : ''} needed
                </span>
              )}
            </div>

            <div className="mt-3 flex items-center justify-between rounded-xl bg-brand-50 px-3 py-2.5">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-700/70">
                  Estimated share
                </p>
                <p className="text-sm font-bold text-brand-800">
                  {taka(row.estimated_share_poisha)}
                  <span className="text-xs font-medium text-brand-700/70"> / seat</span>
                </p>
              </div>
              <p className="text-xs font-semibold text-brand-800">
                {taka(row.estimated_total_poisha)} total
              </p>
            </div>

            {'offer' in row ? (
              requestQuery.isLoading ? (
                <p className="mt-3 text-center text-xs text-ink-400">Preparing your request…</p>
              ) : joinedIds.includes(row.offer.id) ? (
                <p className="mt-3 rounded-xl bg-ink-50 px-3 py-2.5 text-center text-xs font-semibold text-ink-600">
                  Request sent. The provider will respond soon.
                </p>
              ) : (
                <button
                  type="button"
                  onClick={() => joinOfferMutation.mutate({ offerId: row.offer.id, seats: seatsNeeded })}
                  disabled={joinOfferMutation.isPending}
                  className="mt-3 w-full rounded-xl bg-brand-600 px-3 py-2.5 text-xs font-bold text-white disabled:opacity-60"
                >
                  Request to join · {taka(row.estimated_total_poisha)}
                </button>
              )
            ) : sentIds.includes(row.request.id) ? (
              <p className="mt-3 rounded-xl bg-ink-50 px-3 py-2.5 text-center text-xs font-semibold text-ink-600">
                Offer sent. The passenger will respond soon.
              </p>
            ) : vehiclesQuery.isLoading ? (
              <p className="mt-3 text-center text-xs text-ink-400">Checking your vehicles…</p>
            ) : activeVehicle ? (
              <button
                type="button"
                onClick={() => joinRequestMutation.mutate(row.request.id)}
                disabled={joinRequestMutation.isPending}
                className="mt-3 w-full rounded-xl bg-brand-600 px-3 py-2.5 text-xs font-bold text-white disabled:opacity-60"
              >
                Offer to drive · {activeVehicle.make} {activeVehicle.model}
              </button>
            ) : (
              <Link
                to="/app/vehicles"
                className="mt-3 block w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-center text-xs font-bold text-amber-800"
              >
                Add an active vehicle to offer
              </Link>
            )}
          </article>
        )
      })}
    </div>
  )
}
