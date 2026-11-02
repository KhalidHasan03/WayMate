import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api, errorMessage } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type {
  AdminStats,
  AdminVehicleDocument,
  AdminVerificationDocument,
  Paginated,
  Report,
  User,
  Vehicle,
} from '../../types'

type Tab = 'verifications' | 'vehicle-documents' | 'vehicles' | 'reports' | 'users'

const tabs: { key: Tab; label: string }[] = [
  { key: 'verifications', label: 'ID docs' },
  { key: 'vehicle-documents', label: 'Vehicle docs' },
  { key: 'vehicles', label: 'Vehicles' },
  { key: 'reports', label: 'Reports' },
  { key: 'users', label: 'Users' },
]

export function Admin() {
  const user = useAuthStore((s) => s.user)
  const queryClient = useQueryClient()
  const [tab, setTab] = useState<Tab>('verifications')
  const [search, setSearch] = useState('')
  const [error, setError] = useState<string | null>(null)

  const roles = user?.roles ?? []
  const canReview = roles.includes('admin') || roles.includes('moderator')
  const canSuspend = roles.includes('admin')

  const { data: stats } = useQuery({
    queryKey: ['admin-stats'],
    queryFn: async () => (await api.get<{ data: AdminStats }>('/admin/stats')).data.data,
    enabled: canReview,
  })

  const verifications = useQuery({
    queryKey: ['admin-verifications'],
    queryFn: async () =>
      (await api.get<Paginated<AdminVerificationDocument>>('/admin/verification-documents')).data,
    enabled: canReview && tab === 'verifications',
  })

  const vehicleDocs = useQuery({
    queryKey: ['admin-vehicle-documents'],
    queryFn: async () =>
      (await api.get<Paginated<AdminVehicleDocument>>('/admin/vehicle-documents')).data,
    enabled: canReview && tab === 'vehicle-documents',
  })

  const vehicles = useQuery({
    queryKey: ['admin-vehicles'],
    queryFn: async () => (await api.get<Paginated<Vehicle>>('/admin/vehicles')).data,
    enabled: canReview && tab === 'vehicles',
  })

  const reports = useQuery({
    queryKey: ['admin-reports'],
    queryFn: async () => (await api.get<Paginated<Report>>('/admin/reports')).data,
    enabled: canReview && tab === 'reports',
  })

  const users = useQuery({
    queryKey: ['admin-users', search],
    queryFn: async () =>
      (await api.get<Paginated<User>>('/admin/users', { params: { q: search } })).data,
    enabled: canSuspend && tab === 'users',
  })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin-stats'] })
    queryClient.invalidateQueries({ queryKey: ['admin-verifications'] })
    queryClient.invalidateQueries({ queryKey: ['admin-vehicle-documents'] })
    queryClient.invalidateQueries({ queryKey: ['admin-vehicles'] })
    queryClient.invalidateQueries({ queryKey: ['admin-reports'] })
    queryClient.invalidateQueries({ queryKey: ['admin-users'] })
  }

  const review = useMutation({
    mutationFn: async ({
      url,
      decision,
      reason,
    }: {
      url: string
      decision: 'approve' | 'reject'
      reason?: string
    }) => (await api.post(url, { decision, reason })).data,
    onSuccess: () => {
      setError(null)
      refresh()
    },
    onError: (err) => setError(errorMessage(err, 'Review failed.')),
  })

  const setStatus = useMutation({
    mutationFn: async ({ id, status, reason }: { id: number; status: string; reason?: string }) =>
      (await api.post(`/admin/users/${id}/status`, { status, reason })).data,
    onSuccess: () => {
      setError(null)
      refresh()
    },
    onError: (err) => setError(errorMessage(err, 'Could not update this user.')),
  })

  const resolve = useMutation({
    mutationFn: async ({ id, status, note }: { id: number; status: string; note?: string }) =>
      (await api.post(`/admin/reports/${id}/resolve`, { status, note })).data,
    onSuccess: () => {
      setError(null)
      refresh()
    },
    onError: (err) => setError(errorMessage(err, 'Could not resolve this report.')),
  })

  const openFile = async (url: string) => {
    try {
      const response = await api.get(url, { responseType: 'blob' })
      const objectUrl = URL.createObjectURL(response.data as Blob)
      window.open(objectUrl, '_blank', 'noopener')
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    } catch (err) {
      setError(errorMessage(err, 'Could not open the document file.'))
    }
  }

  const reject = (url: string) => {
    const reason = window.prompt('Why is this being rejected?')
    if (!reason?.trim()) return
    review.mutate({ url, decision: 'reject', reason: reason.trim() })
  }

  if (!canReview) {
    return (
      <div className="rounded-2xl border border-ink-200 bg-white p-6 text-center">
        <p className="text-sm font-semibold text-ink-700">Moderators only</p>
        <p className="mt-1 text-xs text-ink-500">
          You need an admin or moderator role to open this page.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-black tracking-tight text-ink-900">Moderation</h1>
        <p className="text-xs text-ink-500">Review documents, vehicles, reports and accounts.</p>
      </div>

      {stats && (
        <div className="grid grid-cols-2 gap-2">
          <Stat label="Pending IDs" value={stats.pending_verifications} />
          <Stat label="Pending vehicle docs" value={stats.pending_vehicle_documents} />
          <Stat label="Pending vehicles" value={stats.pending_vehicles} />
          <Stat label="Open reports" value={stats.open_reports} />
        </div>
      )}

      {error && (
        <p className="rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</p>
      )}

      <div className="flex gap-1.5 overflow-x-auto pb-1">
        {tabs
          .filter((item) => item.key !== 'users' || canSuspend)
          .map((item) => (
            <button
              key={item.key}
              type="button"
              onClick={() => setTab(item.key)}
              className={`shrink-0 rounded-full px-3 py-1.5 text-xs font-bold ${
                tab === item.key
                  ? 'bg-ink-900 text-white'
                  : 'border border-ink-200 bg-white text-ink-600'
              }`}
            >
              {item.label}
            </button>
          ))}
      </div>

      {tab === 'verifications' && (
        <div className="space-y-2">
          {verifications.isLoading && <Loading />}
          {verifications.data?.data.length === 0 && <Empty label="No pending identity documents." />}
          {verifications.data?.data.map((doc) => (
            <Card
              key={doc.id}
              title={`${doc.type.replace('_', ' ')} · ${doc.user?.name ?? 'User'}`}
              subtitle={`${doc.user?.phone ?? ''} · ${formatDateTime(doc.created_at)}`}
            >
              <div className="flex flex-wrap gap-2">
                <Action label="Open file" onClick={() => openFile(doc.file_url)} />
                <Action
                  label="Approve"
                  tone="brand"
                  disabled={review.isPending}
                  onClick={() =>
                    review.mutate({
                      url: `/admin/verification-documents/${doc.id}/review`,
                      decision: 'approve',
                    })
                  }
                />
                <Action
                  label="Reject"
                  tone="danger"
                  disabled={review.isPending}
                  onClick={() => reject(`/admin/verification-documents/${doc.id}/review`)}
                />
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === 'vehicle-documents' && (
        <div className="space-y-2">
          {vehicleDocs.isLoading && <Loading />}
          {vehicleDocs.data?.data.length === 0 && <Empty label="No pending vehicle documents." />}
          {vehicleDocs.data?.data.map((doc) => (
            <Card
              key={doc.id}
              title={`${doc.type.replace('_', ' ')} · ${doc.vehicle?.registration_no ?? 'Vehicle'}`}
              subtitle={`${doc.vehicle?.owner.name ?? ''} · ${formatDateTime(doc.created_at)}`}
            >
              <div className="flex flex-wrap gap-2">
                <Action label="Open file" onClick={() => openFile(doc.file_url)} />
                <Action
                  label="Approve"
                  tone="brand"
                  disabled={review.isPending}
                  onClick={() =>
                    review.mutate({
                      url: `/admin/vehicle-documents/${doc.id}/review`,
                      decision: 'approve',
                    })
                  }
                />
                <Action
                  label="Reject"
                  tone="danger"
                  disabled={review.isPending}
                  onClick={() => reject(`/admin/vehicle-documents/${doc.id}/review`)}
                />
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === 'vehicles' && (
        <div className="space-y-2">
          {vehicles.isLoading && <Loading />}
          {vehicles.data?.data.length === 0 && <Empty label="No vehicles awaiting approval." />}
          {vehicles.data?.data.map((vehicle) => (
            <Card
              key={vehicle.id}
              title={`${vehicle.make} ${vehicle.model} · ${vehicle.registration_no}`}
              subtitle={`${vehicle.user?.name ?? 'Owner'} · ${vehicle.seat_capacity} seat(s) · ${
                vehicle.documents?.length ?? 0
              } document(s)`}
            >
              <div className="flex flex-wrap gap-2">
                <Action
                  label="Approve"
                  tone="brand"
                  disabled={review.isPending}
                  onClick={() =>
                    review.mutate({
                      url: `/admin/vehicles/${vehicle.id}/review`,
                      decision: 'approve',
                    })
                  }
                />
                <Action
                  label="Reject"
                  tone="danger"
                  disabled={review.isPending}
                  onClick={() => reject(`/admin/vehicles/${vehicle.id}/review`)}
                />
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === 'reports' && (
        <div className="space-y-2">
          {reports.isLoading && <Loading />}
          {reports.data?.data.length === 0 && <Empty label="No open reports." />}
          {reports.data?.data.map((report) => (
            <Card
              key={report.id}
              title={`${report.category.replace('_', ' ')} · ${
                report.reported_user?.name ?? 'Unassigned'
              }`}
              subtitle={`${report.reporter?.name ?? 'Reporter'} · trip #${
                report.trip_id ?? '—'
              } · ${formatDateTime(report.created_at)}`}
            >
              <p className="mb-2 text-xs leading-5 text-ink-700">{report.description}</p>
              <div className="flex flex-wrap gap-2">
                <Action
                  label="Resolve"
                  tone="brand"
                  disabled={resolve.isPending}
                  onClick={() => {
                    const note = window.prompt('Resolution note (optional)') ?? undefined
                    resolve.mutate({ id: report.id, status: 'resolved', note })
                  }}
                />
                <Action
                  label="Dismiss"
                  disabled={resolve.isPending}
                  onClick={() => resolve.mutate({ id: report.id, status: 'dismissed' })}
                />
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === 'users' && canSuspend && (
        <div className="space-y-2">
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search name or phone"
            className="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
          {users.isLoading && <Loading />}
          {users.data?.data.length === 0 && <Empty label="No users found." />}
          {users.data?.data.map((row) => (
            <Card
              key={row.id}
              title={`${row.name} · ${row.phone}`}
              subtitle={`${row.status} · ${row.roles?.join(', ') || 'user'} · ${
                row.rating.trips_completed
              } trip(s)`}
            >
              {row.status === 'active' ? (
                <Action
                  label="Suspend"
                  tone="danger"
                  disabled={setStatus.isPending}
                  onClick={() => {
                    const reason = window.prompt('Reason for suspension')
                    if (!reason?.trim()) return
                    setStatus.mutate({ id: row.id, status: 'suspended', reason: reason.trim() })
                  }}
                />
              ) : (
                <Action
                  label="Restore"
                  tone="brand"
                  disabled={setStatus.isPending}
                  onClick={() => setStatus.mutate({ id: row.id, status: 'active' })}
                />
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-2xl border border-ink-200 bg-white px-3 py-3">
      <p className="text-[10px] font-bold uppercase tracking-wide text-ink-400">{label}</p>
      <p className="mt-1 text-xl font-black text-ink-900">{value}</p>
    </div>
  )
}

function Card({
  title,
  subtitle,
  children,
}: {
  title: string
  subtitle: string
  children: React.ReactNode
}) {
  return (
    <div className="rounded-2xl border border-ink-200 bg-white px-4 py-3">
      <p className="text-sm font-bold text-ink-900">{title}</p>
      <p className="mt-0.5 text-[11px] text-ink-500">{subtitle}</p>
      <div className="mt-2">{children}</div>
    </div>
  )
}

function Action({
  label,
  onClick,
  tone = 'neutral',
  disabled,
}: {
  label: string
  onClick: () => void
  tone?: 'neutral' | 'brand' | 'danger'
  disabled?: boolean
}) {
  const tones = {
    neutral: 'border border-ink-200 bg-white text-ink-700',
    brand: 'bg-brand-600 text-white',
    danger: 'bg-red-50 text-red-700',
  }

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`rounded-full px-3 py-1.5 text-xs font-bold disabled:opacity-60 ${tones[tone]}`}
    >
      {label}
    </button>
  )
}

function Loading() {
  return <p className="text-sm text-ink-500">Loading…</p>
}

function Empty({ label }: { label: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-4 py-8 text-center">
      <p className="text-sm font-semibold text-ink-600">{label}</p>
    </div>
  )
}
