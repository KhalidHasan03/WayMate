import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type { Conversation } from '../../types'

export function Messages() {
  const user = useAuthStore((s) => s.user)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['conversations'],
    queryFn: async () =>
      (await api.get<{ data: Conversation[] }>('/conversations')).data.data,
    refetchInterval: 30_000,
  })

  const conversations = data ?? []

  return (
    <div className="animate-rise space-y-4 pb-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Messages</h1>
        <p className="mt-1 text-sm text-ink-500">Trip group chats with your driver and co-passengers.</p>
      </div>

      {isLoading && <p className="py-10 text-center text-sm text-ink-400">Loading conversations…</p>}

      {isError && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          Could not load messages. Please try again.
        </p>
      )}

      {!isLoading && !isError && conversations.length === 0 && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-2xl">
            💬
          </span>
          <h2 className="mt-4 text-base font-bold text-ink-900">No conversations yet</h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            A group chat is created automatically when you join or manage a shared trip.
          </p>
          <Link
            to="/app/trips"
            className="mt-4 inline-block rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white"
          >
            View your trips
          </Link>
        </div>
      )}

      {conversations.length > 0 && (
        <div className="space-y-2">
          {conversations.map((conversation) => {
            const others = conversation.participants.filter((participant) => participant.id !== user?.id)
            const title =
              others.map((participant) => participant.name).filter(Boolean).join(', ') ||
              'Trip group chat'
            const latest = conversation.latest_message

            return (
              <Link
                key={conversation.id}
                to={`/app/chat/${conversation.trip_id}`}
                className="flex items-start gap-3 rounded-2xl border border-ink-200 bg-white p-4 transition hover:border-brand-200 hover:bg-brand-50/30"
              >
                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-600 text-sm font-bold text-white">
                  {title
                    .split(' ')
                    .map((part) => part[0])
                    .join('')
                    .slice(0, 2)
                    .toUpperCase()}
                </span>

                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <p className="truncate text-sm font-bold text-ink-900">{title}</p>
                    {conversation.unread_count > 0 && (
                      <span className="grid min-h-5 min-w-5 shrink-0 place-items-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white">
                        {conversation.unread_count > 9 ? '9+' : conversation.unread_count}
                      </span>
                    )}
                  </div>

                  {conversation.trip && (
                    <p className="mt-0.5 truncate text-[11px] text-ink-500">
                      {conversation.trip.origin?.label ?? 'Pickup'} →{' '}
                      {conversation.trip.destination?.label ?? 'Drop-off'}
                      {conversation.trip.depart_at
                        ? ` · ${formatDateTime(conversation.trip.depart_at)}`
                        : ''}
                    </p>
                  )}

                  <p className="mt-1 truncate text-xs text-ink-500">
                    {latest
                      ? `${latest.mine ? 'You: ' : latest.sender ? `${latest.sender.name}: ` : ''}${latest.body}`
                      : 'No messages yet. Say hello to your group.'}
                  </p>
                </div>
              </Link>
            )
          })}
        </div>
      )}
    </div>
  )
}
