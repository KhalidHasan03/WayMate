import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { errorMessage, api } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import type { AppNotification, NotificationList } from '../../types'

export function Notifications() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => (await api.get<NotificationList>('/notifications')).data,
    refetchInterval: 30_000,
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['notifications'] })

  const markRead = useMutation({
    mutationFn: async (id: string) => (await api.post(`/notifications/${id}/read`)).data,
    onSuccess: invalidate,
  })

  const markAll = useMutation({
    mutationFn: async () => (await api.post('/notifications/read-all')).data,
    onSuccess: invalidate,
  })

  const notifications = data?.data ?? []
  const unread = data?.meta?.unread_count ?? 0

  const open = (item: AppNotification) => {
    if (!item.read_at) markRead.mutate(item.id)
    if (item.url) navigate(item.url)
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-black tracking-tight text-ink-900">Notifications</h1>
          <p className="text-xs text-ink-500">
            {unread > 0 ? `${unread} unread update${unread === 1 ? '' : 's'}` : 'You are all caught up'}
          </p>
        </div>
        {unread > 0 && (
          <button
            type="button"
            onClick={() => markAll.mutate()}
            disabled={markAll.isPending}
            className="rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs font-bold text-ink-700 disabled:opacity-60"
          >
            Mark all read
          </button>
        )}
      </div>

      {error && (
        <p className="rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
          {errorMessage(error, 'Could not load notifications.')}
        </p>
      )}

      {isLoading && <p className="text-sm text-ink-500">Loading notifications…</p>}

      {!isLoading && notifications.length === 0 && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-4 py-8 text-center">
          <p className="text-sm font-semibold text-ink-700">No notifications yet</p>
          <p className="mt-1 text-xs text-ink-500">Join, fare and chat updates will appear here.</p>
        </div>
      )}

      <div className="space-y-2">
        {notifications.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => open(item)}
            className={`w-full rounded-2xl border px-4 py-3 text-left transition ${
              item.read_at
                ? 'border-ink-200 bg-white'
                : 'border-brand-200 bg-brand-50/60'
            }`}
          >
            <div className="flex items-start gap-2">
              {!item.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-600" />}
              <div className="min-w-0 flex-1">
                <p className="text-sm font-bold text-ink-900">{item.title}</p>
                <p className="mt-0.5 text-xs leading-5 text-ink-600">{item.body}</p>
                <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-ink-400">
                  {formatDateTime(item.created_at)}
                </p>
              </div>
            </div>
          </button>
        ))}
      </div>
    </div>
  )
}
