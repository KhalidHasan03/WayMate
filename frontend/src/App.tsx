import { Navigate, Route, Routes } from 'react-router-dom'
import { RequireAuth } from './components/RequireAuth'
import { AppLayout } from './layouts/AppLayout'
import { Login } from './pages/Login'
import { Verify } from './pages/Verify'
import { Welcome } from './pages/Welcome'
import { Admin } from './pages/app/Admin'
import { Chat } from './pages/app/Chat'
import { Dashboard } from './pages/app/Dashboard'
import { Matches } from './pages/app/Matches'
import { Notifications } from './pages/app/Notifications'
import { OfferRide } from './pages/app/OfferRide'
import { Profile } from './pages/app/Profile'
import { RequestRide } from './pages/app/RequestRide'
import { Trips } from './pages/app/Trips'
import { Vehicles } from './pages/app/Vehicles'

function App() {
  return (
    <Routes>
      <Route path="/" element={<Welcome />} />
      <Route path="/login" element={<Login />} />
      <Route path="/verify" element={<Verify />} />

      <Route element={<RequireAuth />}>
        <Route path="/app" element={<AppLayout />}>
          <Route index element={<Dashboard />} />
          <Route path="trips" element={<Trips />} />
          <Route path="request" element={<RequestRide />} />
          <Route path="offer" element={<OfferRide />} />
          <Route path="vehicles" element={<Vehicles />} />
          <Route path="matches/:kind/:id" element={<Matches />} />
          <Route path="chat/:id" element={<Chat />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="admin" element={<Admin />} />
          <Route path="profile" element={<Profile />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
