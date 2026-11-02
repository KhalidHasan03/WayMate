import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { api, errorMessage } from '../lib/api'
import type { Trip } from '../types'

const categories = [
  { value: 'safety', label: 'Safety' },
  { value: 'harassment', label: 'Harassment' },
  { value: 'fraud', label: 'Fraud' },
  { value: 'no_show', label: 'No-show' },
  { value: 'other', label: 'Other' },
]

export function ReportTrip({ trip, currentUserId }: { trip: Trip; currentUserId?: number }) {
  const [open, setOpen] = useState(false)
  const [category, setCategory] = useState('safety')
  const [description, setDescription] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const reportedUserId =
    trip.provider && trip.provider.id !== currentUserId
      ? trip.provider.id
      : trip.participants.find((item) => item.user && item.user.id !== currentUserId)?.user?.id

  const mutation = useMutation({
    mutationFn: async () =>
      api.post('/reports', {
        category,
        description,
        trip_id: trip.id,
        reported_user_id: reportedUserId,
      }),
    onSuccess: () => {
      setSent(true)
      setOpen(false)
      setDescription('')
      setError(null)
    },
    onError: (err) => setError(errorMessage(err, 'Could not send your report.')),
  })

  if (sent) {
    return (
      <p className="mt-2 rounded-xl bg-brand-50 px-3 py-2 text-center text-xs font-semibold text-brand-700">
        Report sent. Our moderation team will review it.
      </p>
    )
  }

  return (
    <div className="mt-2">
      {!open ? (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-center text-xs font-bold text-ink-500"
        >
          Report a problem
        </button>
      ) : (
        <div className="space-y-2 rounded-xl border border-ink-200 bg-ink-50 p-3">
          <select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            className="w-full rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-xs font-semibold text-ink-700"
          >
            {categories.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            rows={3}
            placeholder="What happened? Include enough detail for the safety team."
            className="w-full rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-xs outline-none focus:border-brand-500"
          />
          {error && <p className="text-[11px] font-semibold text-red-700">{error}</p>}
          <div className="flex gap-2">
            <button
              type="button"
              disabled={mutation.isPending || description.trim().length < 10}
              onClick={() => mutation.mutate()}
              className="flex-1 rounded-lg bg-ink-900 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
            >
              {mutation.isPending ? 'Sending…' : 'Send report'}
            </button>
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-600"
            >
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
