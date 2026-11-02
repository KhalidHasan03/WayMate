import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, errorMessage } from '../../lib/api'
import type { ChatMessage, Conversation } from '../../types'

export function Chat() {
  const { id } = useParams<{ id: string }>()
  const queryClient = useQueryClient()
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)
  const bottomRef = useRef<HTMLDivElement>(null)
  const markedRead = useRef(0)

  const conversationQuery = useQuery({
    queryKey: ['conversation', id],
    queryFn: async () =>
      (await api.get<{ data: Conversation }>(`/trips/${id}/conversation`)).data.data,
    enabled: Boolean(id),
    retry: false,
  })

  const conversation = conversationQuery.data

  const messagesQuery = useQuery({
    queryKey: ['messages', conversation?.id],
    queryFn: async () =>
      (
        await api.get<{ data: ChatMessage[] }>(
          `/conversations/${conversation?.id}/messages`,
        )
      ).data.data,
    enabled: Boolean(conversation?.id),
    refetchInterval: 4000,
  })

  const messages = messagesQuery.data ?? []
  const lastMessageId = messages[messages.length - 1]?.id ?? 0

  const sendMutation = useMutation({
    mutationFn: async (text: string) =>
      api.post(`/conversations/${conversation?.id}/messages`, { body: text }),
    onSuccess: () => {
      setError(null)
      setBody('')
      queryClient.invalidateQueries({ queryKey: ['messages', conversation?.id] })
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not send the message.')),
  })

  useEffect(() => {
    if (!conversation?.id || !lastMessageId || markedRead.current === lastMessageId) return

    markedRead.current = lastMessageId

    api
      .post(`/conversations/${conversation.id}/read`, { last_message_id: lastMessageId })
      .then(() => queryClient.invalidateQueries({ queryKey: ['conversations'] }))
      .catch(() => undefined)
  }, [conversation?.id, lastMessageId, queryClient])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [lastMessageId])

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    const text = body.trim()
    if (!text || sendMutation.isPending) return
    sendMutation.mutate(text)
  }

  if (conversationQuery.isLoading) {
    return <p className="py-10 text-center text-sm text-ink-400">Opening trip chat…</p>
  }

  if (conversationQuery.isError || !conversation) {
    return (
      <div className="space-y-3 py-6">
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {conversationQuery.isError
            ? errorMessage(conversationQuery.error, 'Could not open this chat.')
            : 'This chat is not available.'}
        </p>
        <Link to="/app/trips" className="text-xs font-semibold text-brand-700">
          ‹ Back to trips
        </Link>
      </div>
    )
  }

  return (
    <div className="animate-rise flex min-h-[calc(100dvh-11rem)] flex-col">
      <div className="sticky top-[57px] z-10 -mx-4 border-b border-ink-200 bg-ink-50/95 px-4 py-3 backdrop-blur">
        <Link to="/app/trips" className="text-xs font-semibold text-brand-700">
          ‹ Back to trips
        </Link>
        <div className="mt-1 flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h1 className="truncate text-lg font-bold text-ink-900">Trip group chat</h1>
            <p className="truncate text-xs text-ink-500">
              {conversation.trip?.origin?.label ?? 'Pickup'} →{' '}
              {conversation.trip?.destination?.label ?? 'Destination'}
            </p>
          </div>
          <span className="shrink-0 rounded-full bg-white px-2 py-1 text-[10px] font-bold text-ink-500">
            {conversation.participants.length} members
          </span>
        </div>
      </div>

      <div className="flex-1 space-y-2 py-3">
        {messagesQuery.isLoading && (
          <p className="py-8 text-center text-xs text-ink-400">Loading messages…</p>
        )}

        {!messagesQuery.isLoading && messages.length === 0 && (
          <p className="rounded-2xl border border-dashed border-ink-200 bg-white px-4 py-8 text-center text-sm text-ink-500">
            No messages yet. Say hello and agree on a pickup point.
          </p>
        )}

        {messages.map((message) =>
          message.type === 'system' ? (
            <p
              key={message.id}
              className="mx-auto max-w-[85%] rounded-full bg-ink-100 px-3 py-1 text-center text-[11px] font-medium text-ink-500"
            >
              {message.body}
            </p>
          ) : (
            <div
              key={message.id}
              className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}
            >
              <div
                className={`max-w-[82%] rounded-2xl px-3.5 py-2.5 ${
                  message.mine
                    ? 'rounded-br-md bg-brand-600 text-white'
                    : 'rounded-bl-md border border-ink-200 bg-white text-ink-900'
                }`}
              >
                {!message.mine && (
                  <p className="text-[11px] font-bold text-brand-700">
                    {message.sender?.name ?? 'Member'}
                  </p>
                )}
                <p className="whitespace-pre-wrap break-words text-sm leading-relaxed">
                  {message.body}
                </p>
                <p
                  className={`mt-1 text-right text-[10px] ${
                    message.mine ? 'text-white/70' : 'text-ink-400'
                  }`}
                >
                  {new Date(message.created_at).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </p>
              </div>
            </div>
          ),
        )}
        <div ref={bottomRef} />
      </div>

      {error && (
        <p className="mb-2 rounded-xl bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
          {error}
        </p>
      )}

      <form
        onSubmit={submit}
        className="sticky bottom-20 z-10 flex items-end gap-2 rounded-2xl border border-ink-200 bg-white p-2 shadow-lg shadow-ink-900/5"
      >
        <textarea
          value={body}
          onChange={(event) => setBody(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault()
              const text = body.trim()
              if (text) sendMutation.mutate(text)
            }
          }}
          rows={1}
          maxLength={2000}
          placeholder="Message your trip…"
          className="max-h-28 min-h-10 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm text-ink-900 outline-none placeholder:text-ink-400"
        />
        <button
          type="submit"
          disabled={!body.trim() || sendMutation.isPending}
          className="rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-bold text-white disabled:opacity-50"
        >
          {sendMutation.isPending ? 'Sending…' : 'Send'}
        </button>
      </form>
    </div>
  )
}
