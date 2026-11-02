import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef, useState } from 'react'
import { api, errorMessage } from '../../lib/api'
import type { Vehicle } from '../../types'

const inputClass =
  'w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100'
const labelClass = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-500'
const MAX_VEHICLE_YEAR = new Date().getFullYear() + 1

const statusStyles: Record<string, string> = {
  active: 'bg-brand-50 text-brand-700',
  pending: 'bg-amber-50 text-amber-700',
  rejected: 'bg-red-50 text-red-700',
  suspended: 'bg-ink-100 text-ink-600',
}

export function Vehicles() {
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [type, setType] = useState('motorcycle')
  const [make, setMake] = useState('')
  const [model, setModel] = useState('')
  const [year, setYear] = useState('')
  const [registrationNo, setRegistrationNo] = useState('')
  const [color, setColor] = useState('')
  const [seatCapacity, setSeatCapacity] = useState(1)

  const vehiclesQuery = useQuery({
    queryKey: ['vehicles'],
    queryFn: async () => (await api.get<{ data: Vehicle[] }>('/vehicles')).data.data,
  })

  const createMutation = useMutation({
    mutationFn: async () =>
      api.post('/vehicles', {
        type,
        make,
        model,
        ...(year ? { year: Number(year) } : {}),
        registration_no: registrationNo,
        ...(color ? { color } : {}),
        seat_capacity: seatCapacity,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vehicles'] })
      queryClient.invalidateQueries({ queryKey: ['me'] })
      setShowForm(false)
      setMake('')
      setModel('')
      setYear('')
      setRegistrationNo('')
      setColor('')
      setError(null)
    },
    onError: (err) => setError(errorMessage(err, 'Could not add the vehicle.')),
  })

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)
    createMutation.mutate()
  }

  const vehicles = vehiclesQuery.data ?? []

  return (
    <div className="animate-rise space-y-4 pb-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-ink-900">Vehicles</h1>
          <p className="mt-1 text-sm text-ink-500">
            Add the vehicle you use to offer rides. Active vehicles only.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowForm((value) => !value)}
          className="shrink-0 rounded-xl bg-brand-600 px-3.5 py-2.5 text-xs font-bold text-white"
        >
          {showForm ? 'Close' : 'Add vehicle'}
        </button>
      </div>

      {showForm && (
        <form onSubmit={submit} className="space-y-3 rounded-2xl border border-ink-200 bg-white p-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="type" className={labelClass}>
                Type
              </label>
              <select
                id="type"
                value={type}
                onChange={(event) => {
                  setType(event.target.value)
                  setSeatCapacity(event.target.value === 'car' ? 3 : 1)
                }}
                className={inputClass}
              >
                <option value="motorcycle">Motorcycle</option>
                <option value="scooter">Scooter</option>
                <option value="car">Car</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label htmlFor="capacity" className={labelClass}>
                Seats
              </label>
              <select
                id="capacity"
                value={seatCapacity}
                onChange={(event) => setSeatCapacity(Number(event.target.value))}
                className={inputClass}
              >
                {[1, 2, 3, 4, 5, 6].map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="make" className={labelClass}>
                Make
              </label>
              <input
                id="make"
                value={make}
                onChange={(event) => setMake(event.target.value)}
                placeholder="Bajaj"
                className={inputClass}
                required
              />
            </div>
            <div>
              <label htmlFor="model" className={labelClass}>
                Model
              </label>
              <input
                id="model"
                value={model}
                onChange={(event) => setModel(event.target.value)}
                placeholder="Pulsar 150"
                className={inputClass}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="year" className={labelClass}>
                Year
              </label>
              <input
                id="year"
                type="number"
                inputMode="numeric"
                min="1980"
                max={MAX_VEHICLE_YEAR}
                value={year}
                onChange={(event) => setYear(event.target.value)}
                placeholder="2021"
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="color" className={labelClass}>
                Colour
              </label>
              <input
                id="color"
                value={color}
                onChange={(event) => setColor(event.target.value)}
                placeholder="Black"
                className={inputClass}
              />
            </div>
          </div>

          <div>
            <label htmlFor="registration" className={labelClass}>
              Registration number
            </label>
            <input
              id="registration"
              value={registrationNo}
              onChange={(event) => setRegistrationNo(event.target.value.toUpperCase())}
              placeholder="DHAKA METRO HA 12-3456"
              className={inputClass}
              required
            />
          </div>

          {error && (
            <p className="rounded-xl bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{error}</p>
          )}

          <button
            type="submit"
            disabled={createMutation.isPending}
            className="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-60"
          >
            {createMutation.isPending ? 'Adding…' : 'Add vehicle'}
          </button>
          <p className="text-center text-[11px] text-ink-400">
            New vehicles start as pending until the documents are reviewed.
          </p>
        </form>
      )}

      {vehiclesQuery.isLoading && (
        <p className="py-8 text-center text-sm text-ink-400">Loading your vehicles…</p>
      )}

      {!vehiclesQuery.isLoading && vehicles.length === 0 && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">
            🚗
          </span>
          <h2 className="mt-4 text-base font-bold text-ink-900">No vehicles yet</h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            Add a motorcycle, scooter or car to start offering rides.
          </p>
        </div>
      )}

      {vehicles.map((vehicle) => (
        <VehicleCard key={vehicle.id} vehicle={vehicle} />
      ))}
    </div>
  )
}

function VehicleCard({ vehicle }: { vehicle: Vehicle }) {
  const queryClient = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [docType, setDocType] = useState('registration')
  const [error, setError] = useState<string | null>(null)

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData()
      formData.append('type', docType)
      formData.append('file', file)
      return api.post(`/vehicles/${vehicle.id}/documents`, formData)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vehicles'] })
      setError(null)
      if (fileRef.current) fileRef.current.value = ''
    },
    onError: (err) => setError(errorMessage(err, 'Upload failed.')),
  })

  const docTypeLabel: Record<string, string> = {
    registration: 'Registration',
    tax_token: 'Tax token',
    insurance: 'Insurance',
    fitness: 'Fitness',
  }

  return (
    <section className="rounded-2xl border border-ink-200 bg-white p-4">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-bold text-ink-900">
            {vehicle.make} {vehicle.model}
            {vehicle.year ? ` · ${vehicle.year}` : ''}
          </p>
          <p className="mt-0.5 text-xs text-ink-500">
            {vehicle.registration_no} · {vehicle.type} · {vehicle.seat_capacity} seats
            {vehicle.color ? ` · ${vehicle.color}` : ''}
          </p>
        </div>
        <span
          className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold capitalize ${
            statusStyles[vehicle.status] ?? 'bg-ink-100 text-ink-600'
          }`}
        >
          {vehicle.status}
        </span>
      </div>

      {vehicle.rejection_reason && (
        <p className="mt-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
          {vehicle.rejection_reason}
        </p>
      )}

      <div className="mt-3 flex flex-wrap gap-1.5">
        {(vehicle.documents ?? []).map((document) => (
          <span
            key={document.id}
            className="rounded-full bg-ink-50 px-2.5 py-1 text-[11px] font-medium text-ink-600"
          >
            {docTypeLabel[document.type] ?? document.type} · {document.status}
          </span>
        ))}
        {(vehicle.documents ?? []).length === 0 && (
          <span className="text-[11px] text-ink-400">No documents uploaded yet.</span>
        )}
      </div>

      <div className="mt-3 flex items-center gap-2">
        <select
          value={docType}
          onChange={(event) => setDocType(event.target.value)}
          className="rounded-xl border border-ink-200 bg-white px-2.5 py-2 text-xs font-semibold text-ink-700"
        >
          {Object.entries(docTypeLabel).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <input
          ref={fileRef}
          type="file"
          accept="image/jpeg,image/png,application/pdf"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            if (file) uploadMutation.mutate(file)
          }}
        />
        <button
          type="button"
          onClick={() => fileRef.current?.click()}
          disabled={uploadMutation.isPending}
          className="flex-1 rounded-xl border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-700 hover:bg-ink-50 disabled:opacity-60"
        >
          {uploadMutation.isPending ? 'Uploading…' : 'Upload document'}
        </button>
      </div>

      {error && <p className="mt-2 text-xs font-medium text-red-700">{error}</p>}
    </section>
  )
}
