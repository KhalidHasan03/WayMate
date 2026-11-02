import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Logo } from '../components/Logo'
import { api, errorMessage } from '../lib/api'
import { useAuthStore } from '../stores/authStore'
import type { AuthResponse, OtpRequestResponse } from '../types'

export function Verify() {
  const navigate = useNavigate()
  const location = useLocation()
  const setAuth = useAuthStore((s) => s.setAuth)
  const phone =
    (location.state as { phone?: string } | null)?.phone ??
    sessionStorage.getItem('waymate.pending_phone')

  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [secondsLeft, setSecondsLeft] = useState(60)
  const [debugCode, setDebugCode] = useState<string | null>(
    () =>
      (location.state as { debugCode?: string } | null)?.debugCode ??
      sessionStorage.getItem('waymate.debug_otp'),
  )
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!phone) navigate('/login', { replace: true })
  }, [phone, navigate])

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    if (secondsLeft <= 0) return
    const timer = window.setInterval(() => setSecondsLeft((s) => Math.max(0, s - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [secondsLeft])

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!phone) return
    setError(null)
    setIsSubmitting(true)

    try {
      const { data } = await api.post<AuthResponse>('/auth/otp/verify', {
        phone,
        code,
        device_name: 'web',
      })
      sessionStorage.removeItem('waymate.pending_phone')
      sessionStorage.removeItem('waymate.debug_otp')
      setAuth(data.user, data.token)
      navigate('/app', { replace: true })
    } catch (err) {
      setError(errorMessage(err, 'The code could not be verified.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  const resend = async () => {
    if (!phone || secondsLeft > 0) return
    setError(null)
    try {
      const { data } = await api.post<OtpRequestResponse>('/auth/otp/request', { phone })

      if (data.code) {
        sessionStorage.setItem('waymate.debug_otp', data.code)
      } else {
        sessionStorage.removeItem('waymate.debug_otp')
      }

      setDebugCode(data.code ?? null)
      setSecondsLeft(60)
    } catch (err) {
      setError(errorMessage(err, 'Could not resend the code yet.'))
    }
  }

  return (
    <main className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-5 py-10">
      <Link to="/" className="mx-auto mb-8">
        <Logo size={52} withWordmark />
      </Link>

      <div className="animate-rise rounded-3xl border border-ink-200 bg-white p-6 shadow-xl shadow-ink-900/5 sm:p-8">
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Check your messages</h1>
        <p className="mt-2 text-sm leading-relaxed text-ink-500">
          {debugCode ? (
            <>
              Local development mode: no SMS is sent. Use the code below for{' '}
              <span className="font-semibold text-ink-700">{phone}</span>.
            </>
          ) : (
            <>
              We sent a 6-digit code to{' '}
              <span className="font-semibold text-ink-700">{phone}</span>. It expires in 5
              minutes.
            </>
          )}
        </p>

        {debugCode && (
          <button
            type="button"
            onClick={() => setCode(debugCode)}
            className="mt-4 flex w-full items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left transition hover:border-amber-300 hover:bg-amber-100"
          >
            <span>
              <span className="block text-xs font-semibold tracking-wide text-amber-700 uppercase">
                Local dev code
              </span>
              <span className="mt-0.5 block text-sm text-amber-800">Tap to fill the code.</span>
            </span>
            <span className="font-mono text-xl font-bold tracking-[0.2em] text-amber-900">
              {debugCode}
            </span>
          </button>
        )}

        <form onSubmit={submit} className="mt-6 space-y-5">
          <div>
            <label htmlFor="code" className="mb-1.5 block text-sm font-semibold text-ink-700">
              Verification code
            </label>
            <input
              ref={inputRef}
              id="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              className="w-full rounded-2xl border border-ink-200 bg-white px-4 py-3.5 text-center text-2xl font-bold tracking-[0.4em] text-ink-900 outline-none transition placeholder:text-ink-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
            />
          </div>

          {error && (
            <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={code.length !== 6 || isSubmitting}
            className="w-full rounded-2xl bg-brand-600 px-5 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isSubmitting ? 'Verifying…' : 'Verify and continue'}
          </button>
        </form>

        <div className="mt-5 flex items-center justify-between text-sm">
          <button
            type="button"
            onClick={() => navigate('/login', { replace: true })}
            className="font-medium text-ink-500 hover:text-ink-900"
          >
            Change number
          </button>
          <button
            type="button"
            onClick={resend}
            disabled={secondsLeft > 0}
            className="font-semibold text-brand-700 disabled:cursor-not-allowed disabled:text-ink-400"
          >
            {secondsLeft > 0 ? `Resend in ${secondsLeft}s` : 'Resend code'}
          </button>
        </div>
      </div>
    </main>
  )
}
