import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { BellIcon, HomeIcon, PlusIcon, RouteIcon, UserIcon } from '../components/icons'
import { Logo } from '../components/Logo'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/authStore'
import type { MeResponse, NotificationList } from '../types'

const navItems = [
  { to: '/app', label: 'Home', icon: HomeIcon, end: true },
  { to: '/app/trips', label: 'Trips', icon: RouteIcon },
  { to: '/app/request', label: 'Request', icon: PlusIcon, center: true },
  { to: '/app/profile', label: 'Profile', icon: UserIcon },
]

export function AppLayout() {
  const user = useAuthStore((s) => s.user)
  const setUser = useAuthStore((s) => s.setUser)
  const navigate = useNavigate()

  const { data } = useQuery({
    queryKey: ['me'],
    queryFn: async () => (await api.get<MeResponse>('/me')).data,
    staleTime: 60_000,
  })

  useEffect(() => {
    if (data?.data) setUser(data.data)
  }, [data, setUser])

  const { data: notifications } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => (await api.get<NotificationList>('/notifications')).data,
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

  const unread = notifications?.meta?.unread_count ?? 0

  const initials = (user?.name ?? 'W')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  return (
    <div className="min-h-dvh bg-ink-50">
      <header className="sticky top-0 z-20 border-b border-ink-200/70 bg-white/90 backdrop-blur">
        <div className="mx-auto flex w-full max-w-lg items-center justify-between px-4 py-3">
          <Logo size={34} withWordmark />
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => navigate('/app/notifications')}
              className="relative grid h-9 w-9 place-items-center rounded-full bg-ink-100 text-ink-600"
              aria-label="Open notifications"
            >
              <BellIcon />
              {unread > 0 && (
                <span className="absolute -right-0.5 -top-0.5 grid min-h-4 min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[9px] font-bold text-white">
                  {unread > 9 ? '9+' : unread}
                </span>
              )}
            </button>
            <button
              type="button"
              onClick={() => navigate('/app/profile')}
              className="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white"
              aria-label="Open profile"
            >
              {initials}
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-lg px-4 pb-28 pt-4">
        <Outlet />
      </main>

      <nav className="fixed inset-x-0 bottom-0 z-20 border-t border-ink-200/70 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
        <div className="mx-auto grid w-full max-w-lg grid-cols-4 items-end px-2 py-2">
          {navItems.map((item) => {
            const Icon = item.icon

            if (item.center) {
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className="flex flex-col items-center gap-1 pb-1 text-[11px] font-semibold text-brand-700"
                >
                  <span className="grid h-12 w-12 -translate-y-3 place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30">
                    <Icon className="h-6 w-6" />
                  </span>
                  {item.label}
                </NavLink>
              )
            }

            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `flex flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold transition ${
                    isActive ? 'text-brand-700' : 'text-ink-400 hover:text-ink-700'
                  }`
                }
              >
                <Icon />
                {item.label}
              </NavLink>
            )
          })}
        </div>
      </nav>
    </div>
  )
}
