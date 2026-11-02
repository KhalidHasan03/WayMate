import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { LogoutIcon, ShieldIcon } from '../../components/icons'
import { api, errorMessage } from '../../lib/api'
import { useAuthStore } from '../../stores/authStore'
import type { VerificationDocument } from '../../types'

export function Profile() {
  const user = useAuthStore((s) => s.user)
  const logout = useAuthStore((s) => s.logout)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [loggingOut, setLoggingOut] = useState(false)
  const nidRef = useRef<HTMLInputElement>(null)
  const licenseRef = useRef<HTMLInputElement>(null)
  const [docError, setDocError] = useState<string | null>(null)

  const docsQuery = useQuery({
    queryKey: ['verification-documents'],
    queryFn: async () =>
      (await api.get<{ data: VerificationDocument[] }>('/me/documents')).data.data,
  })

  const uploadMutation = useMutation({
    mutationFn: async ({ type, file }: { type: string; file: File }) => {
      const formData = new FormData()
      formData.append('type', type)
      formData.append('file', file)
      return api.post('/me/documents', formData)
    },
    onSuccess: () => {
      setDocError(null)
      queryClient.invalidateQueries({ queryKey: ['verification-documents'] })
      queryClient.invalidateQueries({ queryKey: ['me'] })
    },
    onError: (err) => setDocError(errorMessage(err, 'Upload failed.')),
  })

  const initials = (user?.name ?? 'W')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  const handleLogout = async () => {
    setLoggingOut(true)
    try {
      await api.post('/auth/logout')
    } catch {
      // Token may already be invalid; clear locally regardless.
    } finally {
      logout()
      navigate('/login', { replace: true })
    }
  }

  return (
    <div className="animate-rise space-y-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Profile</h1>
        <p className="mt-1 text-sm text-ink-500">Your account, verification and preferences.</p>
      </div>

      <section className="rounded-2xl border border-ink-200 bg-white p-5">
        <div className="flex items-center gap-4">
          <span className="grid h-16 w-16 place-items-center rounded-2xl bg-brand-600 text-xl font-bold text-white">
            {initials}
          </span>
          <div className="min-w-0">
            <h2 className="truncate text-lg font-bold text-ink-900">{user?.name ?? 'WayMate user'}</h2>
            <p className="text-sm text-ink-500">{user?.phone}</p>
            <div className="mt-1 flex flex-wrap gap-1.5">
              {user?.phone_verified_at && (
                <span className="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700">
                  Phone verified
                </span>
              )}
              <span
                className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                  user?.identity_verified
                    ? 'bg-brand-50 text-brand-700'
                    : 'bg-amber-50 text-amber-700'
                }`}
              >
                {user?.identity_verified ? 'Identity verified' : 'Identity unverified'}
              </span>
            </div>
          </div>
        </div>

        <dl className="mt-5 grid grid-cols-3 divide-x divide-ink-200 rounded-xl bg-ink-50 py-3 text-center">
          <div>
            <dt className="text-[11px] font-medium uppercase tracking-wide text-ink-400">Trips</dt>
            <dd className="mt-0.5 text-base font-bold text-ink-900">{user?.rating.trips_completed ?? 0}</dd>
          </div>
          <div>
            <dt className="text-[11px] font-medium uppercase tracking-wide text-ink-400">Rating</dt>
            <dd className="mt-0.5 text-base font-bold text-ink-900">
              {user?.rating.average ? user.rating.average.toFixed(1) : '—'}
            </dd>
          </div>
          <div>
            <dt className="text-[11px] font-medium uppercase tracking-wide text-ink-400">No-shows</dt>
            <dd className="mt-0.5 text-base font-bold text-ink-900">{user?.rating.no_shows ?? 0}</dd>
          </div>
        </dl>
      </section>

      <section className="rounded-2xl border border-ink-200 bg-white">
        <div className="flex items-center gap-3 px-4 py-3.5">
          <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700">
            <ShieldIcon />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-ink-900">Verification</p>
            <p className="text-xs text-ink-500">
              {user?.identity_verified
                ? 'Your NID and licence are verified.'
                : 'Upload your NID and driving licence to offer rides.'}
            </p>
          </div>
        </div>

        <div className="space-y-2 border-t border-ink-100 px-4 py-3.5">
          {(docsQuery.data ?? []).map((document) => (
            <div
              key={document.id}
              className="flex items-center justify-between rounded-xl bg-ink-50 px-3 py-2"
            >
              <span className="text-xs font-semibold capitalize text-ink-700">
                {document.type.replace('_', ' ')}
              </span>
              <span className="text-[11px] font-bold capitalize text-ink-500">
                {document.status}
              </span>
            </div>
          ))}

          <input
            ref={nidRef}
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            className="hidden"
            onChange={(event) => {
              const file = event.target.files?.[0]
              if (file) uploadMutation.mutate({ type: 'nid', file })
            }}
          />
          <input
            ref={licenseRef}
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            className="hidden"
            onChange={(event) => {
              const file = event.target.files?.[0]
              if (file) uploadMutation.mutate({ type: 'driving_license', file })
            }}
          />

          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              onClick={() => nidRef.current?.click()}
              disabled={uploadMutation.isPending}
              className="rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-xs font-bold text-ink-700 hover:bg-ink-50 disabled:opacity-60"
            >
              Upload NID
            </button>
            <button
              type="button"
              onClick={() => licenseRef.current?.click()}
              disabled={uploadMutation.isPending}
              className="rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-xs font-bold text-ink-700 hover:bg-ink-50 disabled:opacity-60"
            >
              Upload licence
            </button>
          </div>

          {docError && <p className="text-xs font-medium text-red-700">{docError}</p>}
          <p className="text-[11px] leading-relaxed text-ink-400">
            Documents are private and reviewed by our team. JPG, PNG or PDF up to 5 MB.
          </p>
        </div>

        <Link
          to="/app/vehicles"
          className="flex items-center justify-between border-t border-ink-100 px-4 py-3.5"
        >
          <div>
            <p className="text-sm font-semibold text-ink-900">Vehicles & documents</p>
            <p className="text-xs text-ink-500">Add a vehicle and upload its papers.</p>
          </div>
          <span className="text-ink-400">›</span>
        </Link>

        {(user?.roles?.includes('admin') || user?.roles?.includes('moderator')) && (
          <Link
            to="/app/admin"
            className="flex items-center justify-between border-t border-ink-100 px-4 py-3.5"
          >
            <div>
              <p className="text-sm font-semibold text-ink-900">Moderation</p>
              <p className="text-xs text-ink-500">Review documents, reports and accounts.</p>
            </div>
            <span className="text-ink-400">›</span>
          </Link>
        )}

        <div className="border-t border-ink-100 px-4 py-3.5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-semibold text-ink-900">Language</p>
              <p className="text-xs text-ink-500">App language</p>
            </div>
            <span className="rounded-full bg-ink-100 px-3 py-1 text-xs font-semibold text-ink-700">
              {user?.locale === 'bn' ? 'বাংলা' : 'English'}
            </span>
          </div>
        </div>
      </section>

      <button
        type="button"
        onClick={handleLogout}
        disabled={loggingOut}
        className="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-200 bg-white px-4 py-3.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
      >
        <LogoutIcon className="h-4 w-4" />
        {loggingOut ? 'Signing out…' : 'Sign out'}
      </button>

      <p className="pb-2 text-center text-[11px] text-ink-400">WayMate · cost sharing for Bangladesh</p>
    </div>
  )
}
