import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { LocationSearch } from '../../components/LocationSearch'
import { api, errorMessage } from '../../lib/api'
import { defaultDepartInput } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type { GeoPlace, Vehicle } from '../../types'

const inputClass =
  'w-full rounded-2xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100'
const labelClass = 'mb-1.5 block text-sm font-semibold text-ink-700'

export function OfferRide() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)

  const vehiclesQuery = useQuery({
    queryKey: ['vehicles'],
    queryFn: async () => (await api.get<{ data: Vehicle[] }>('/vehicles')).data.data,
  })

  const activeVehicles = (vehiclesQuery.data ?? []).filter((v) => v.status === 'active')

  const [vehicleId, setVehicleId] = useState<number | null>(null)
  const [origin, setOrigin] = useState<GeoPlace | null>(null)
  const [destination, setDestination] = useState<GeoPlace | null>(null)
  const [departAt, setDepartAt] = useState(defaultDepartInput())
  const [seatsTotal, setSeatsTotal] = useState(1)
  const [costTotal, setCostTotal] = useState('')
  const [providerParticipates, setProviderParticipates] = useState(true)
  const [womenOnly, setWomenOnly] = useState(false)
  const [notes, setNotes] = useState('')
  const [error, setError] = useState<string | null>(null)

  const effectiveVehicleId = vehicleId ?? activeVehicles[0]?.id ?? null
  const selectedVehicle = activeVehicles.find((v) => v.id === effectiveVehicleId)
  const maxSeats = Math.min(selectedVehicle?.seat_capacity ?? 4, 4)
  const effectiveSeats = Math.min(seatsTotal, maxSeats)

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        vehicle_id: effectiveVehicleId,
        origin_lat: origin!.lat,
        origin_lng: origin!.lng,
        origin_label: origin!.label,
        dest_lat: destination!.lat,
        dest_lng: destination!.lng,
        dest_label: destination!.label,
        depart_at: new Date(departAt).toISOString(),
        seats_total: effectiveSeats,
        cost_total_poisha: Math.round(Number(costTotal) * 100),
        provider_participates: providerParticipates,
        women_only: womenOnly,
        notes: notes.trim() || null,
      }

      return api.post('/ride-offers', payload)
    },
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['ride-offers'] })
      const id = (response.data as { data: { id: number } }).data.id
      navigate(`/app/matches/offer/${id}`)
    },
    onError: (err) => setError(errorMessage(err, 'Could not publish the offer.')),
  })

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)

    if (!user?.identity_verified) {
      setError('Verify your identity before offering rides.')
      return
    }

    if (!effectiveVehicleId) {
      setError('Add and activate a vehicle first.')
      return
    }

    if (!origin || !destination) {
      setError('Pick both a start point and a destination.')
      return
    }

    if (origin.lat === destination.lat && origin.lng === destination.lng) {
      setError('Start and destination must be different.')
      return
    }

    if (!costTotal || Number(costTotal) <= 0) {
      setError('Enter the total trip cost you want to share.')
      return
    }

    mutation.mutate()
  }

  if (vehiclesQuery.isLoading) {
    return <p className="py-10 text-center text-sm text-ink-400">Loading vehicles…</p>
  }

  if (activeVehicles.length === 0) {
    return (
      <div className="animate-rise space-y-5">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-ink-900">Offer a ride</h1>
          <p className="mt-1 text-sm text-ink-500">Share your route and the seats you have free.</p>
        </div>

        <div className="rounded-3xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">
            🏍️
          </span>
          <h2 className="mt-4 text-base font-bold text-ink-900">Add your vehicle first</h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            You need an active vehicle to publish seats. Add one, then our team verifies it.
          </p>
          <Link
            to="/app/vehicles"
            className="mt-5 inline-block rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white"
          >
            Manage vehicles
          </Link>
        </div>
      </div>
    )
  }

  return (
    <form onSubmit={submit} className="animate-rise space-y-5 pb-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Offer a ride</h1>
        <p className="mt-1 text-sm text-ink-500">
          Publish your empty seats. Riders share fuel, tolls and extras.
        </p>
      </div>

      {!user?.identity_verified && (
        <Link
          to="/app/profile"
          className="block rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
        >
          Verify your NID and licence before publishing. Tap to upload documents.
        </Link>
      )}

      <section className="space-y-4 rounded-2xl border border-ink-200 bg-white p-4">
        <div>
          <label htmlFor="vehicle" className={labelClass}>
            Vehicle
          </label>
          <select
            id="vehicle"
            value={effectiveVehicleId ?? ''}
            onChange={(event) => setVehicleId(Number(event.target.value))}
            className={inputClass}
          >
            {activeVehicles.map((vehicle) => (
              <option key={vehicle.id} value={vehicle.id}>
                {vehicle.make} {vehicle.model} · {vehicle.registration_no}
              </option>
            ))}
          </select>
        </div>

        <LocationSearch
          label="Starting from"
          tone="origin"
          placeholder="Where does your trip start?"
          value={origin}
          onChange={setOrigin}
        />

        <LocationSearch
          label="Going to"
          tone="destination"
          placeholder="Where are you heading?"
          value={destination}
          onChange={setDestination}
        />

        <div>
          <label htmlFor="depart" className={labelClass}>
            Leaving at
          </label>
          <input
            id="depart"
            type="datetime-local"
            value={departAt}
            onChange={(event) => setDepartAt(event.target.value)}
            className={inputClass}
            required
          />
        </div>
      </section>

      <section className="space-y-4 rounded-2xl border border-ink-200 bg-white p-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label htmlFor="seats" className={labelClass}>
              Seats to share
            </label>
            <select
              id="seats"
              value={effectiveSeats}
              onChange={(event) => setSeatsTotal(Number(event.target.value))}
              className={inputClass}
            >
              {Array.from({ length: maxSeats }, (_, index) => index + 1).map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="cost" className={labelClass}>
              Total cost (৳)
            </label>
            <input
              id="cost"
              type="number"
              min="1"
              step="10"
              inputMode="numeric"
              value={costTotal}
              onChange={(event) => setCostTotal(event.target.value)}
              placeholder="Fuel + tolls"
              className={inputClass}
              required
            />
          </div>
        </div>

        <label className="flex items-start gap-3 rounded-xl bg-ink-50 p-3">
          <input
            type="checkbox"
            checked={providerParticipates}
            onChange={(event) => setProviderParticipates(event.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600"
          />
          <span>
            <span className="block text-sm font-semibold text-ink-800">I am riding too</span>
            <span className="block text-xs text-ink-500">
              Your share is included when splitting the cost across everyone in the vehicle.
            </span>
          </span>
        </label>

        {user?.gender === 'female' && (
          <label className="flex items-center gap-3 text-sm font-medium text-ink-700">
            <input
              type="checkbox"
              checked={womenOnly}
              onChange={(event) => setWomenOnly(event.target.checked)}
              className="h-4 w-4 rounded border-ink-300 text-brand-600"
            />
            Women-only ride
          </label>
        )}

        <div>
          <label htmlFor="offer-notes" className={labelClass}>
            Notes
          </label>
          <textarea
            id="offer-notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            rows={3}
            maxLength={500}
            placeholder="Pickup landmark, luggage space, rules…"
            className={inputClass}
          />
        </div>
      </section>

      {error && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{error}</p>
      )}

      <button
        type="submit"
        disabled={mutation.isPending || !user?.identity_verified}
        className="w-full rounded-2xl bg-brand-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-60"
      >
        {mutation.isPending ? 'Publishing…' : 'Publish ride'}
      </button>

      <p className="text-center text-[11px] leading-relaxed text-ink-400">
        Only share seats you can legally carry. Never exceed your vehicle's capacity.
      </p>
    </form>
  )
}
