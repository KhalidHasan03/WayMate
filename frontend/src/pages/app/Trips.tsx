import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FarePanel } from '../../components/FarePanel'
import { ReportTrip } from '../../components/ReportTrip'
import { api, errorMessage } from '../../lib/api'
import { durationLabel, formatDateTime, taka } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type {
  JoinRequest,
  JoinTarget,
  Paginated,
  RideOffer,
  RideRequest,
  Trip,
} from '../../types'

type Tab = 'upcoming' | 'past'

function statusBadge(status: string) {
  const styles: Record<string, string> = {
    open: 'bg-brand-50 text-brand-700',
    draft: 'bg-ink-100 text-ink-600',
    forming: 'bg-sky-50 text-sky-700',
    ready: 'bg-sky-50 text-sky-700',
    in_progress: 'bg-indigo-50 text-indigo-700',
    completed: 'bg-ink-100 text-ink-600',
    cancelled: 'bg-red-50 text-red-600',
    declined: 'bg-red-50 text-red-600',
    withdrawn: 'bg-ink-100 text-ink-500',
    expired: 'bg-amber-50 text-amber-700',
    accepted: 'bg-brand-50 text-brand-700',
    pending: 'bg-amber-50 text-amber-700',
  }

  return (
    <span
      className={`rounded-full px-2.5 py-1 text-[11px] font-bold capitalize ${
        styles[status] ?? 'bg-ink-100 text-ink-600'
      }`}
    >
      {status.replace('_', ' ')}
    </span>
  )
}

type LifecycleAction = 'start' | 'complete' | 'cancel'

export function Trips() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const [tab, setTab] = useState<Tab>('upcoming')
  const [error, setError] = useState<string | null>(null)

  const requestsQuery = useQuery({
    queryKey: ['ride-requests'],
    queryFn: async () => (await api.get<Paginated<RideRequest>>('/ride-requests')).data,
  })

  const offersQuery = useQuery({
    queryKey: ['ride-offers'],
    queryFn: async () => (await api.get<Paginated<RideOffer>>('/ride-offers')).data,
  })

  const tripsQuery = useQuery({
    queryKey: ['trips'],
    queryFn: async () => (await api.get<Paginated<Trip>>('/trips')).data,
  })

  const incomingQuery = useQuery({
    queryKey: ['joins', 'incoming'],
    queryFn: async () => (await api.get<Paginated<JoinRequest>>('/joins/incoming')).data,
  })

  const outgoingQuery = useQuery({
    queryKey: ['joins', 'outgoing'],
    queryFn: async () => (await api.get<Paginated<JoinRequest>>('/joins/outgoing')).data,
  })

  const cancelMutation = useMutation({
    mutationFn: async ({ kind, id }: { kind: 'request' | 'offer'; id: number }) => {
      const path = kind === 'request' ? `/ride-requests/${id}/cancel` : `/ride-offers/${id}/cancel`
      return api.post(path)
    },
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['ride-requests'] })
      queryClient.invalidateQueries({ queryKey: ['ride-offers'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not cancel.')),
  })

  const tripMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: LifecycleAction }) =>
      api.post(`/trips/${id}/${action}`, action === 'cancel' ? { reason: 'Cancelled by trip manager' } : {}),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['trips'] })
      queryClient.invalidateQueries({ queryKey: ['fare'] })
      queryClient.invalidateQueries({ queryKey: ['ride-requests'] })
      queryClient.invalidateQueries({ queryKey: ['ride-offers'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not update the trip.')),
  })

  const joinMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'accept' | 'decline' | 'withdraw' }) =>
      api.post(`/joins/${id}/${action}`),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['joins'] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
      queryClient.invalidateQueries({ queryKey: ['ride-requests'] })
      queryClient.invalidateQueries({ queryKey: ['ride-offers'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not update the join request.')),
  })

  const requests = requestsQuery.data?.data ?? []
  const offers = offersQuery.data?.data ?? []
  const trips = tripsQuery.data?.data ?? []

  const pendingIncoming = (incomingQuery.data?.data ?? []).filter(
    (join) => join.status === 'pending',
  )
  const pendingOutgoing = (outgoingQuery.data?.data ?? []).filter(
    (join) => join.status === 'pending',
  )

  const isUpcomingRequest = (request: RideRequest) =>
    request.status === 'open' || request.status === 'draft' || Boolean(request.active_trip)
  const isUpcomingOffer = (offer: RideOffer) =>
    offer.status === 'open' || offer.status === 'in_progress'
  const isUpcomingTrip = (trip: Trip) =>
    ['forming', 'ready', 'in_progress'].includes(trip.status)

  const visibleRequests = requests.filter((request) =>
    tab === 'upcoming' ? isUpcomingRequest(request) : !isUpcomingRequest(request),
  )
  const visibleOffers = offers.filter((offer) =>
    tab === 'upcoming' ? isUpcomingOffer(offer) : !isUpcomingOffer(offer),
  )
  const visibleTrips = trips.filter((trip) =>
    tab === 'upcoming' ? isUpcomingTrip(trip) : !isUpcomingTrip(trip),
  )
  const visibleJoins =
    tab === 'upcoming' ? [...pendingIncoming, ...pendingOutgoing] : []

  const isLoading = requestsQuery.isLoading || offersQuery.isLoading || tripsQuery.isLoading
  const isEmpty =
    visibleRequests.length === 0 &&
    visibleOffers.length === 0 &&
    visibleTrips.length === 0 &&
    visibleJoins.length === 0

  return (
    <div className="animate-rise space-y-4 pb-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Your trips</h1>
        <p className="mt-1 text-sm text-ink-500">Requested seats, offered rides and shared trips.</p>
      </div>

      <div className="flex gap-2">
        {(['upcoming', 'past'] as Tab[]).map((value) => (
          <button
            key={value}
            type="button"
            onClick={() => setTab(value)}
            className={`rounded-full px-3.5 py-1.5 text-xs font-semibold capitalize transition ${
              tab === value
                ? 'bg-brand-600 text-white'
                : 'border border-ink-200 bg-white text-ink-500'
            }`}
          >
            {value}
          </button>
        ))}
      </div>

      {error && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{error}</p>
      )}

      {isLoading && <p className="py-8 text-center text-sm text-ink-400">Loading trips…</p>}

      {!isLoading && isEmpty && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">
            🧭
          </span>
          <h2 className="mt-4 text-base font-bold text-ink-900">
            {tab === 'upcoming' ? 'No upcoming trips' : 'Nothing in your history yet'}
          </h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            {tab === 'upcoming'
              ? 'When you request or offer a ride, your active trips and their fare status will show up here.'
              : 'Completed and cancelled trips will be kept here for your records.'}
          </p>
          {tab === 'upcoming' && (
            <div className="mt-5 flex justify-center gap-2">
              <Link
                to="/app/request"
                className="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white"
              >
                Request a seat
              </Link>
              <Link
                to="/app/offer"
                className="rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700"
              >
                Offer a ride
              </Link>
            </div>
          )}
        </div>
      )}

      {visibleJoins.length > 0 && (
        <section className="space-y-2">
          <h2 className="px-1 text-xs font-bold uppercase tracking-wide text-ink-400">
            Join requests
          </h2>
          {pendingIncoming.map((join) => (
            <JoinCard
              key={`incoming-${join.id}`}
              join={join}
              incoming
              busy={joinMutation.isPending}
              onAction={(action) => joinMutation.mutate({ id: join.id, action })}
            />
          ))}
          {pendingOutgoing.map((join) => (
            <JoinCard
              key={`outgoing-${join.id}`}
              join={join}
              busy={joinMutation.isPending}
              onAction={(action) => joinMutation.mutate({ id: join.id, action })}
            />
          ))}
        </section>
      )}

      {visibleTrips.length > 0 && (
        <section className="space-y-2">
          <h2 className="px-1 text-xs font-bold uppercase tracking-wide text-ink-400">
            Shared trips
          </h2>
          {visibleTrips.map((trip) => (
            <TripCard
              key={`trip-${trip.id}`}
              trip={trip}
              currentUserId={user?.id}
              busy={tripMutation.isPending}
              onLifecycle={(action) => {
                if (action === 'cancel' && !window.confirm('Cancel this shared trip?')) return
                tripMutation.mutate({ id: trip.id, action })
              }}
            />
          ))}
        </section>
      )}

      {visibleRequests.length > 0 && (
        <section className="space-y-2">
          <h2 className="px-1 text-xs font-bold uppercase tracking-wide text-ink-400">
            Seat requests
          </h2>
          {visibleRequests.map((request) => (
            <RequestCard
              key={`request-${request.id}`}
              request={request}
              cancelling={cancelMutation.isPending}
              onCancel={() => cancelMutation.mutate({ kind: 'request', id: request.id })}
            />
          ))}
        </section>
      )}

      {visibleOffers.length > 0 && (
        <section className="space-y-2">
          <h2 className="px-1 text-xs font-bold uppercase tracking-wide text-ink-400">
            Rides you offer
          </h2>
          {visibleOffers.map((offer) => (
            <OfferCard
              key={`offer-${offer.id}`}
              offer={offer}
              cancelling={cancelMutation.isPending}
              onCancel={() => cancelMutation.mutate({ kind: 'offer', id: offer.id })}
            />
          ))}
        </section>
      )}
    </div>
  )
}

function RouteLine({ origin, destination }: { origin: string; destination: string }) {
  return (
    <div className="mt-2 space-y-1.5">
      <div className="flex items-start gap-2">
        <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
        <p className="truncate text-sm font-semibold text-ink-900">{origin}</p>
      </div>
      <div className="ml-[3px] h-2.5 w-px bg-ink-200" />
      <div className="flex items-start gap-2">
        <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-ink-900" />
        <p className="truncate text-sm font-semibold text-ink-900">{destination}</p>
      </div>
    </div>
  )
}

function JoinCard({
  join,
  incoming = false,
  busy,
  onAction,
}: {
  join: JoinRequest
  incoming?: boolean
  busy: boolean
  onAction: (action: 'accept' | 'decline' | 'withdraw') => void
}) {
  const target: JoinTarget | null = join.ride_offer ?? join.ride_request ?? null

  return (
    <article className="rounded-2xl border border-ink-200 bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-bold text-ink-900">
            {incoming
              ? join.role === 'provider'
                ? `${join.user?.name ?? 'A driver'} offered to drive`
                : `${join.user?.name ?? 'A passenger'} wants to join`
              : join.role === 'provider'
                ? 'You offered to drive'
                : 'You asked to join'}
          </p>
          <p className="mt-0.5 text-xs text-ink-500">
            {join.seats} seat{join.seats > 1 ? 's' : ''}
            {join.intended_depart_at ? ` · ${formatDateTime(join.intended_depart_at)}` : ''}
          </p>
        </div>
        {statusBadge(join.status)}
      </div>

      {target && (
        <RouteLine origin={target.origin.label} destination={target.destination.label} />
      )}

      {join.vehicle && (
        <p className="mt-2 text-xs text-ink-500">
          {join.vehicle.make} {join.vehicle.model} · {join.vehicle.registration_no}
        </p>
      )}

      {join.message && <p className="mt-2 text-xs text-ink-500">“{join.message}”</p>}

      {join.consented_contribution_poisha !== null && join.role !== 'provider' && (
        <p className="mt-2 text-xs font-semibold text-brand-700">
          Contribution {taka(join.consented_contribution_poisha)}
        </p>
      )}

      <div className="mt-3 flex gap-2">
        {incoming ? (
          <>
            <button
              type="button"
              onClick={() => onAction('accept')}
              disabled={busy}
              className="flex-1 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
            >
              Accept
            </button>
            <button
              type="button"
              onClick={() => onAction('decline')}
              disabled={busy}
              className="flex-1 rounded-xl border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-700 disabled:opacity-60"
            >
              Decline
            </button>
          </>
        ) : (
          <button
            type="button"
            onClick={() => onAction('withdraw')}
            disabled={busy}
            className="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-700 disabled:opacity-60"
          >
            Withdraw request
          </button>
        )}
      </div>
    </article>
  )
}

function TripCard({
  trip,
  currentUserId,
  busy,
  onLifecycle,
}: {
  trip: Trip
  currentUserId?: number
  busy: boolean
  onLifecycle: (action: LifecycleAction) => void
}) {
  const canManage = trip.coordinator?.id === currentUserId || trip.provider?.id === currentUserId
  const canStart = canManage && ['forming', 'ready'].includes(trip.status)
  const canComplete = canManage && trip.status === 'in_progress'
  const canCancel = canManage && ['forming', 'ready', 'in_progress'].includes(trip.status)

  return (
    <article className="rounded-2xl border border-ink-200 bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-sm font-bold text-ink-900">
            {trip.source === 'offer' ? 'Shared ride' : 'Your requested ride'}
          </p>
          <p className="mt-0.5 text-xs text-ink-500">
            {trip.depart_at ? formatDateTime(trip.depart_at) : 'Departure pending'}
          </p>
        </div>
        {statusBadge(trip.status)}
      </div>

      {trip.origin && trip.destination && (
        <RouteLine origin={trip.origin.label} destination={trip.destination.label} />
      )}

      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
        <span>{trip.participants.length} member(s)</span>
        {trip.provider && <span>Driver: {trip.provider.name}</span>}
        {trip.vehicle && <span>{trip.vehicle.registration_no}</span>}
        {trip.pool_capacity !== null && (
          <span>
            {trip.seats_allocated ?? 0} of {trip.pool_capacity} seats
          </span>
        )}
      </div>

      {trip.participants.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {trip.participants.map((participant) => (
            <span
              key={participant.id}
              className="rounded-full bg-ink-50 px-2.5 py-1 text-[11px] font-medium text-ink-600"
            >
              {participant.user?.name ?? 'Member'}
              {participant.role === 'provider' ? ' · driver' : ` · ${participant.seats} seat(s)`}
            </span>
          ))}
        </div>
      )}

      {!['cancelled', 'expired'].includes(trip.status) && trip.participants.length > 0 && (
        <>
          <FarePanel trip={trip} currentUserId={currentUserId} />
          <Link
            to={`/app/chat/${trip.id}`}
            className="mt-2 block w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-center text-xs font-bold text-ink-700"
          >
            Open trip group chat
          </Link>
          <ReportTrip trip={trip} currentUserId={currentUserId} />
        </>
      )}

      {(canStart || canComplete || canCancel) && (
        <div className="mt-3 flex gap-2">
          {canStart && (
            <button
              type="button"
              onClick={() => onLifecycle('start')}
              disabled={busy}
              className="flex-1 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
            >
              Start trip
            </button>
          )}
          {canComplete && (
            <button
              type="button"
              onClick={() => onLifecycle('complete')}
              disabled={busy}
              className="flex-1 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
            >
              Complete trip
            </button>
          )}
          {canCancel && (
            <button
              type="button"
              onClick={() => onLifecycle('cancel')}
              disabled={busy}
              className="rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-600 disabled:opacity-60"
            >
              Cancel
            </button>
          )}
        </div>
      )}
    </article>
  )
}

function RequestCard({
  request,
  cancelling,
  onCancel,
}: {
  request: RideRequest
  cancelling: boolean
  onCancel: () => void
}) {
  const cancellable = request.status === 'open' && !request.active_trip

  return (
    <article className="rounded-2xl border border-ink-200 bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs font-semibold uppercase tracking-wide text-ink-400">
          {formatDateTime(request.depart_at)}
        </p>
        {statusBadge(request.active_trip?.status ?? request.status)}
      </div>

      <RouteLine origin={request.origin.label} destination={request.destination.label} />

      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
        <span>
          {request.seats_needed} seat{request.seats_needed > 1 ? 's' : ''}
        </span>
        <span>{durationLabel(request.duration_s)}</span>
        <span>{(request.distance_m / 1000).toFixed(1)} km</span>
        {request.budget_per_seat_poisha !== null && (
          <span>Budget {taka(request.budget_per_seat_poisha)}/seat</span>
        )}
        {(request.join_requests_count ?? 0) > 0 && (
          <span>{request.join_requests_count} join request(s)</span>
        )}
      </div>

      {request.notes && <p className="mt-2 text-xs text-ink-500">{request.notes}</p>}

      {request.status === 'open' && (
        <Link
          to={`/app/matches/request/${request.id}`}
          className="mt-3 block w-full rounded-xl bg-brand-600 px-3 py-2 text-center text-xs font-bold text-white"
        >
          Find matches
        </Link>
      )}

      {cancellable && (
        <button
          type="button"
          onClick={onCancel}
          disabled={cancelling}
          className="mt-2 w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 disabled:opacity-60"
        >
          Cancel request
        </button>
      )}
    </article>
  )
}

function OfferCard({
  offer,
  cancelling,
  onCancel,
}: {
  offer: RideOffer
  cancelling: boolean
  onCancel: () => void
}) {
  const cancellable = offer.status === 'open'

  return (
    <article className="rounded-2xl border border-ink-200 bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs font-semibold uppercase tracking-wide text-ink-400">
          {formatDateTime(offer.depart_at)}
        </p>
        {statusBadge(offer.status)}
      </div>

      <RouteLine origin={offer.origin.label} destination={offer.destination.label} />

      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
        <span>
          {offer.seats_available} of {offer.seats_total} seat
          {offer.seats_total > 1 ? 's' : ''} free
        </span>
        <span>{durationLabel(offer.duration_s)}</span>
        <span>{(offer.distance_m / 1000).toFixed(1)} km</span>
        <span>Total {taka(offer.cost_total_poisha)}</span>
      </div>

      {offer.vehicle && (
        <p className="mt-2 text-xs text-ink-500">
          {offer.vehicle.make} {offer.vehicle.model} · {offer.vehicle.registration_no}
        </p>
      )}

      {offer.notes && <p className="mt-2 text-xs text-ink-500">{offer.notes}</p>}

      {offer.status === 'open' && (
        <Link
          to={`/app/matches/offer/${offer.id}`}
          className="mt-3 block w-full rounded-xl bg-brand-600 px-3 py-2 text-center text-xs font-bold text-white"
        >
          Find passengers
        </Link>
      )}

      {cancellable && (
        <button
          type="button"
          onClick={onCancel}
          disabled={cancelling}
          className="mt-2 w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 disabled:opacity-60"
        >
          Cancel offer
        </button>
      )}
    </article>
  )
}
