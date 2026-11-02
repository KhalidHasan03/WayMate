import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { Logo } from '../components/Logo'
import { api, errorMessage } from '../lib/api'
import { useAuthStore } from '../stores/authStore'
import type { OtpRequestResponse } from '../types'

const schema = z.object({
  phone: z
    .string()
    .min(1, 'Enter your mobile number')
    .regex(/^(?:\+?880|0)?1[3-9]\d{8}$/, 'Use a valid Bangladeshi mobile number'),
})

type FormValues = z.infer<typeof schema>

export function Login() {
  const navigate = useNavigate()
  const token = useAuthStore((s) => s.token)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  if (token) {
    return <Navigate to="/app" replace />
  }

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    try {
      const { data } = await api.post<OtpRequestResponse>('/auth/otp/request', {
        phone: values.phone,
      })
      sessionStorage.setItem('waymate.pending_phone', values.phone)

      if (data.code) {
        sessionStorage.setItem('waymate.debug_otp', data.code)
      } else {
        sessionStorage.removeItem('waymate.debug_otp')
      }

      navigate('/verify', {
        state: { phone: values.phone, debugCode: data.code },
      })
    } catch (error) {
      setServerError(errorMessage(error, 'Could not send the code. Try again.'))
    }
  }

  return (
    <main className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-5 py-10">
      <Link to="/" className="mx-auto mb-8">
        <Logo size={52} withWordmark />
      </Link>

      <div className="animate-rise rounded-3xl border border-ink-200 bg-white p-6 shadow-xl shadow-ink-900/5 sm:p-8">
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Sign in or sign up</h1>
        <p className="mt-2 text-sm leading-relaxed text-ink-500">
          Enter your Bangladeshi mobile number. We will text you a 6-digit code — no
          password needed.
        </p>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-5" noValidate>
          <div>
            <label htmlFor="phone" className="mb-1.5 block text-sm font-semibold text-ink-700">
              Mobile number
            </label>
            <div className="flex overflow-hidden rounded-2xl border border-ink-200 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
              <span className="grid place-items-center border-r border-ink-200 bg-ink-50 px-3 text-sm font-semibold text-ink-500">
                +880
              </span>
              <input
                id="phone"
                type="tel"
                inputMode="numeric"
                autoComplete="tel"
                placeholder="1712 345 678"
                className="w-full bg-transparent px-4 py-3.5 text-base text-ink-900 outline-none placeholder:text-ink-400"
                {...register('phone')}
              />
            </div>
            {errors.phone && <p className="mt-1.5 text-xs font-medium text-red-600">{errors.phone.message}</p>}
          </div>

          {serverError && (
            <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-2xl bg-brand-600 px-5 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isSubmitting ? 'Sending code…' : 'Send verification code'}
          </button>
        </form>

        <p className="mt-5 text-center text-xs leading-relaxed text-ink-400">
          By continuing you agree to WayMate's terms and acknowledge that this is a
          cost-sharing platform.
        </p>
      </div>

      <p className="mt-6 text-center text-sm text-ink-500">
        Just browsing?{' '}
        <Link to="/" className="font-semibold text-brand-700 hover:underline">
          Back home
        </Link>
      </p>
    </main>
  )
}
