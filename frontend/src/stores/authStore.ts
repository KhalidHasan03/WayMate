import { create } from 'zustand'
import { tokenStorage } from '../lib/tokenStorage'
import type { User } from '../types'

interface AuthState {
  user: User | null
  token: string | null
  setAuth: (user: User, token: string) => void
  setUser: (user: User) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>((set) => ({
  token: tokenStorage.getToken(),
  user: tokenStorage.getUser<User>(),
  setAuth: (user, token) => {
    tokenStorage.setToken(token)
    tokenStorage.setUser(user)
    set({ user, token })
  },
  setUser: (user) => {
    tokenStorage.setUser(user)
    set({ user })
  },
  logout: () => {
    tokenStorage.clear()
    set({ user: null, token: null })
  },
}))

if (typeof window !== 'undefined') {
  window.addEventListener('waymate:unauthorized', () => {
    useAuthStore.getState().logout()
  })
}
