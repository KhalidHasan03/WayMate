import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useState } from 'react'
import { api, errorMessage } from '../lib/api'
import { taka } from '../lib/format'
import type { Contribution, Fare, Trip } from '../types'

const inputClass =
  'w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-100'

interface ItemDraft {
  label: string
  amount: string
  split_method: 'equal' | 'provider_only'
}

const contributionStatus: Record<string, string> = {
  pending: 'bg-amber-50 text-amber-700',
  cash_paid: 'bg-brand-50 text-brand-700',
  waived: 'bg-sky-50 text-sky-700',
  void: 'bg-ink-100 text-ink-500',
}

export function FarePanel({ trip, currentUserId }: { trip: Trip; currentUserId?: number }) {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [providerParticipates, setProviderParticipates] = useState(
    trip.offer?.provider_participates ?? true,
  )
  const [items, setItems] = useState<ItemDraft[]>([
    { label: 'Fuel', amount: '', split_method: 'equal' },
  ])
  const [showAdjustment, setShowAdjustment] = useState(false)
  const [adjustmentAmount, setAdjustmentAmount] = useState('')
  const [adjustmentReason, setAdjustmentReason] = useState('')

  const fareQuery = useQuery({
    queryKey: ['fare', trip.id],
    queryFn: async () => (await api.get<{ data: Fare | null }>(`/trips/${trip.id}/fare`)).data.data,
    retry: false,
  })

  const fare = fareQuery.data ?? null
  const is404 = axios.isAxiosError(fareQuery.error) && fareQuery.error.response?.status === 404
  const isCoordinator = trip.coordinator?.id === currentUserId
  const canSettle = isCoordinator || trip.provider?.id === currentUserId
  const participant = trip.participants.find((row) => row.user?.id === currentUserId)
  const isParticipant = Boolean(participant)
  const canEdit =
    isCoordinator && ['forming', 'ready', 'in_progress', 'completed'].includes(trip.status)
  const editable = canEdit && (!fare || fare.status === 'agreeing')

  const saveMutation = useMutation({
    mutationFn: async () =>
      api.put(`/trips/${trip.id}/fare`, {
        provider_participates: providerParticipates,
        items: items
          .filter((item) => item.label.trim() && Number(item.amount) > 0)
          .map((item) => ({
            label: item.label.trim(),
            amount_poisha: Math.round(Number(item.amount) * 100),
            split_method: item.split_method,
          })),
      }),
    onSuccess: () => {
      setError(null)
      setEditing(false)
      queryClient.invalidateQueries({ queryKey: ['fare', trip.id] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not save the fare.')),
  })

  const agreeMutation = useMutation({
    mutationFn: async () => api.post(`/trips/${trip.id}/fare/agree`),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['fare', trip.id] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not agree to the fare.')),
  })

  const settleMutation = useMutation({
    mutationFn: async ({
      contribution,
      status,
    }: {
      contribution: Contribution
      status: 'cash_paid' | 'waived' | 'void'
    }) => api.post(`/trips/${trip.id}/fare/contributions/${contribution.id}/settle`, { status }),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['fare', trip.id] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not update the contribution.')),
  })

  const proposeAdjustmentMutation = useMutation({
    mutationFn: async () =>
      api.post(`/trips/${trip.id}/fare/adjustments`, {
        new_total_poisha: Math.round(Number(adjustmentAmount) * 100),
        reason: adjustmentReason.trim() || null,
      }),
    onSuccess: () => {
      setError(null)
      setShowAdjustment(false)
      setAdjustmentAmount('')
      setAdjustmentReason('')
      queryClient.invalidateQueries({ queryKey: ['fare', trip.id] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not propose the adjustment.')),
  })

  const voteAdjustmentMutation = useMutation({
    mutationFn: async ({ id, vote }: { id: number; vote: 'accept' | 'reject' }) =>
      api.post(`/trips/${trip.id}/fare/adjustments/${id}/vote`, { vote }),
    onSuccess: () => {
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['fare', trip.id] })
      queryClient.invalidateQueries({ queryKey: ['trips'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not record your vote.')),
  })

  if (fareQuery.isLoading) {
    return <p className="mt-3 text-xs text-ink-400">Loading fare…</p>
  }

  if (!fare && !is404) {
    return (
      <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
        Could not load the fare.
      </p>
    )
  }

  const updateItem = (index: number, patch: Partial<ItemDraft>) =>
    setItems((current) => current.map((item, i) => (i === index ? { ...item, ...patch } : item)))

  const startEditing = () => {
    if (fare) {
      setProviderParticipates(fare.provider_participates)
      setItems(
        fare.items.length > 0
          ? fare.items.map((item) => ({
              label: item.label,
              amount: (item.amount_poisha / 100).toString(),
              split_method: item.split_method === 'provider_only' ? 'provider_only' : 'equal',
            }))
          : [{ label: 'Fuel', amount: '', split_method: 'equal' }],
      )
    }
    setEditing(true)
  }

  const pendingAdjustment = fare?.adjustments?.find((row) => row.status === 'pending')
  const myVote = pendingAdjustment?.votes.find((vote) => vote.user?.id === currentUserId)
  const canVote =
    Boolean(pendingAdjustment) &&
    isParticipant &&
    pendingAdjustment?.proposer?.id !== currentUserId &&
    !myVote

  return (
    <div className="mt-3 rounded-xl border border-ink-200 bg-ink-50/60 p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-bold uppercase tracking-wide text-ink-500">Cost share</p>
        {fare && (
          <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold capitalize text-ink-600">
            {fare.status.replace('_', ' ')} · v{fare.fare_version}
          </span>
        )}
      </div>

      {error && <p className="mt-2 rounded-lg bg-red-50 px-2.5 py-2 text-xs text-red-700">{error}</p>}

      {!fare && !editable && (
        <p className="mt-2 text-xs text-ink-500">The coordinator has not set up the fare yet.</p>
      )}

      {fare && (
        <>
          <div className="mt-2 flex items-end justify-between">
            <div>
              <p className="text-lg font-bold text-ink-900">{taka(fare.estimated_total_poisha)}</p>
              <p className="text-[11px] text-ink-500">
                {fare.participant_count} paying member(s)
                {fare.provider_participates ? ' · driver included' : ' · driver not included'}
              </p>
            </div>
            {fare.status === 'agreed' && fare.agreed_total_poisha !== null && (
              <p className="text-xs font-semibold text-brand-700">
                Agreed {taka(fare.agreed_total_poisha)}
              </p>
            )}
          </div>

          <ul className="mt-2 space-y-1">
            {fare.items.map((item) => (
              <li key={item.id} className="flex justify-between text-xs text-ink-600">
                <span>
                  {item.label}
                  {item.split_method === 'provider_only' && (
                    <span className="ml-1 text-[10px] text-ink-400">driver pays</span>
                  )}
                </span>
                <span className="font-semibold">{taka(item.amount_poisha)}</span>
              </li>
            ))}
          </ul>

          {fare.contributions.length > 0 && (
            <div className="mt-3 space-y-1.5">
              {fare.contributions.map((contribution) => (
                <div
                  key={contribution.id}
                  className="flex items-center justify-between gap-2 rounded-lg bg-white px-2.5 py-2"
                >
                  <div className="min-w-0">
                    <p className="truncate text-xs font-semibold text-ink-800">
                      {contribution.user?.name ?? 'Member'}
                    </p>
                    <p className="text-[11px] text-ink-500">{taka(contribution.amount_poisha)}</p>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <span
                      className={`rounded-full px-2 py-0.5 text-[10px] font-bold capitalize ${
                        contributionStatus[contribution.status] ?? 'bg-ink-100 text-ink-600'
                      }`}
                    >
                      {contribution.status.replace('_', ' ')}
                    </span>
                    {canSettle && fare.status !== 'settled' && contribution.status === 'pending' && (
                      <>
                        <button
                          type="button"
                          onClick={() => settleMutation.mutate({ contribution, status: 'cash_paid' })}
                          disabled={settleMutation.isPending}
                          className="rounded-lg bg-brand-600 px-2 py-1 text-[10px] font-bold text-white disabled:opacity-60"
                        >
                          Paid
                        </button>
                        <button
                          type="button"
                          onClick={() => settleMutation.mutate({ contribution, status: 'waived' })}
                          disabled={settleMutation.isPending}
                          className="rounded-lg border border-ink-200 bg-white px-2 py-1 text-[10px] font-bold text-ink-600 disabled:opacity-60"
                        >
                          Waive
                        </button>
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}

          {fare.status === 'agreeing' &&
            isParticipant &&
            ((participant?.consented_fare_version ?? 0) >= fare.fare_version ? (
              <p className="mt-2 rounded-lg bg-white px-2.5 py-2 text-center text-[11px] font-semibold text-ink-600">
                You agreed to version {fare.fare_version}. Waiting for others.
              </p>
            ) : (
              <button
                type="button"
                onClick={() => agreeMutation.mutate()}
                disabled={agreeMutation.isPending}
                className="mt-2 w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
              >
                I agree to this split
              </button>
            ))}

          {fare.status === 'settled' && (
            <p className="mt-2 rounded-lg bg-brand-50 px-2.5 py-2 text-center text-[11px] font-semibold text-brand-700">
              Fare settled. Thank you for sharing the cost.
            </p>
          )}

          {pendingAdjustment && (
            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2.5">
              <p className="text-[11px] font-bold uppercase tracking-wide text-amber-800">
                Fare change proposed
              </p>
              <p className="mt-1 text-xs text-amber-900">
                {pendingAdjustment.proposer?.name ?? 'A member'} proposed{' '}
                <span className="font-bold">{taka(pendingAdjustment.new_total_poisha)}</span>
                {pendingAdjustment.reason ? ` — ${pendingAdjustment.reason}` : ''}
              </p>

              {pendingAdjustment.votes.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {pendingAdjustment.votes.map((vote) => (
                    <span
                      key={`${vote.user?.id}-${vote.vote}`}
                      className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                        vote.vote === 'accept'
                          ? 'bg-brand-100 text-brand-800'
                          : 'bg-red-100 text-red-700'
                      }`}
                    >
                      {vote.user?.name ?? 'Member'}: {vote.vote}
                    </span>
                  ))}
                </div>
              )}

              {canVote ? (
                <div className="mt-2 flex gap-1.5">
                  <button
                    type="button"
                    onClick={() =>
                      voteAdjustmentMutation.mutate({ id: pendingAdjustment.id, vote: 'accept' })
                    }
                    disabled={voteAdjustmentMutation.isPending}
                    className="flex-1 rounded-lg bg-brand-600 px-2 py-1.5 text-[11px] font-bold text-white disabled:opacity-60"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    onClick={() =>
                      voteAdjustmentMutation.mutate({ id: pendingAdjustment.id, vote: 'reject' })
                    }
                    disabled={voteAdjustmentMutation.isPending}
                    className="flex-1 rounded-lg border border-red-200 bg-white px-2 py-1.5 text-[11px] font-bold text-red-600 disabled:opacity-60"
                  >
                    Reject
                  </button>
                </div>
              ) : (
                <p className="mt-2 text-[11px] font-medium text-amber-800">
                  {myVote
                    ? `You voted ${myVote.vote}. Waiting for others.`
                    : pendingAdjustment.proposer?.id === currentUserId
                      ? 'Waiting for the other members to vote.'
                      : 'Only trip members can vote.'}
                </p>
              )}
            </div>
          )}

          {!pendingAdjustment && fare.status === 'agreed' && isParticipant && !showAdjustment && (
            <button
              type="button"
              onClick={() => {
                setShowAdjustment(true)
                setAdjustmentAmount((fare.estimated_total_poisha / 100).toString())
              }}
              className="mt-2 text-[11px] font-semibold text-brand-700"
            >
              Propose a fare change
            </button>
          )}

          {!pendingAdjustment && fare.status === 'agreed' && isParticipant && showAdjustment && (
            <div className="mt-2 space-y-1.5 rounded-lg bg-white p-2">
              <input
                className={inputClass}
                type="number"
                min="1"
                value={adjustmentAmount}
                onChange={(event) => setAdjustmentAmount(event.target.value)}
                placeholder="New total in taka"
              />
              <input
                className={inputClass}
                value={adjustmentReason}
                onChange={(event) => setAdjustmentReason(event.target.value)}
                placeholder="Reason (optional)"
                maxLength={300}
              />
              <div className="flex gap-1.5">
                <button
                  type="button"
                  onClick={() => proposeAdjustmentMutation.mutate()}
                  disabled={
                    proposeAdjustmentMutation.isPending ||
                    !adjustmentAmount ||
                    Number(adjustmentAmount) <= 0
                  }
                  className="flex-1 rounded-lg bg-brand-600 px-2 py-1.5 text-[11px] font-bold text-white disabled:opacity-60"
                >
                  {proposeAdjustmentMutation.isPending ? 'Sending…' : 'Send for vote'}
                </button>
                <button
                  type="button"
                  onClick={() => setShowAdjustment(false)}
                  className="rounded-lg border border-ink-200 px-2 py-1.5 text-[11px] font-semibold text-ink-600"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {editable && (
            <button
              type="button"
              onClick={() => (editing ? setEditing(false) : startEditing())}
              className="mt-2 text-[11px] font-semibold text-brand-700"
            >
              {editing ? 'Close editor' : 'Recalculate fare'}
            </button>
          )}
        </>
      )}

      {!fare && editable && !editing && (
        <button
          type="button"
          onClick={() => setEditing(true)}
          className="mt-2 w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white"
        >
          Set up the cost split
        </button>
      )}

      {editing && editable && (
        <div className="mt-3 space-y-2">
          {items.map((item, index) => (
            <div key={index} className="space-y-1.5 rounded-lg bg-white p-2">
              <input
                className={inputClass}
                value={item.label}
                onChange={(event) => updateItem(index, { label: event.target.value })}
                placeholder="Fuel, toll, parking…"
              />
              <div className="flex gap-1.5">
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  step="1"
                  value={item.amount}
                  onChange={(event) => updateItem(index, { amount: event.target.value })}
                  placeholder="Amount in taka"
                />
                <select
                  className={inputClass}
                  value={item.split_method}
                  onChange={(event) =>
                    updateItem(index, {
                      split_method: event.target.value as ItemDraft['split_method'],
                    })
                  }
                >
                  <option value="equal">Split equally</option>
                  <option value="provider_only">Driver pays</option>
                </select>
              </div>
              {items.length > 1 && (
                <button
                  type="button"
                  onClick={() => setItems((current) => current.filter((_, i) => i !== index))}
                  className="text-[11px] font-semibold text-red-600"
                >
                  Remove item
                </button>
              )}
            </div>
          ))}

          <button
            type="button"
            onClick={() =>
              setItems((current) => [
                ...current,
                { label: '', amount: '', split_method: 'equal' },
              ])
            }
            className="w-full rounded-lg border border-dashed border-ink-300 px-3 py-1.5 text-[11px] font-semibold text-ink-500"
          >
            + Add cost item
          </button>

          <label className="flex items-center gap-2 text-xs font-medium text-ink-600">
            <input
              type="checkbox"
              checked={providerParticipates}
              onChange={(event) => setProviderParticipates(event.target.checked)}
              className="h-4 w-4 rounded border-ink-300 text-brand-600"
            />
            The driver shares the cost too
          </label>

          <button
            type="button"
            onClick={() => saveMutation.mutate()}
            disabled={
              saveMutation.isPending ||
              items.every((item) => !item.label.trim() || Number(item.amount) <= 0)
            }
            className="w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-60"
          >
            {saveMutation.isPending ? 'Saving…' : 'Save split for agreement'}
          </button>
        </div>
      )}
    </div>
  )
}
