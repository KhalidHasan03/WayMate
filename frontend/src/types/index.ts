export interface UserProfile {
  bio: string | null
  preferred_vehicle_type: string
  women_only_opt_in: boolean
  notify_prefs: Record<string, boolean> | null
  terms_accepted_at: string | null
  cost_share_disclaimer_at: string | null
}

export interface User {
  id: number
  name: string
  phone: string
  email: string | null
  gender: string
  dob: string | null
  avatar_url: string | null
  locale: string
  status: string
  phone_verified_at: string | null
  identity_verified: boolean
  rating: {
    average: number
    count: number
    trips_completed: number
    no_shows: number
  }
  roles?: string[]
  profile?: UserProfile | null
  created_at: string
}

export interface AuthResponse {
  token: string
  token_type: string
  user: User
}

export interface OtpRequestResponse {
  message: string
  expires_in: number
  code?: string
}

export interface MeResponse {
  data: User
}

export interface GeoPlace {
  label: string
  lat: number
  lng: number
  provider: string
}

export interface Vehicle {
  id: number
  type: string
  make: string
  model: string
  year: number | null
  registration_no: string
  color: string | null
  seat_capacity: number
  status: string
  verified_at: string | null
  rejection_reason: string | null
  user?: { id: number; name: string; phone: string }
  documents?: VehicleDocument[]
  created_at: string
}

export interface VerificationDocument {
  id: number
  type: string
  status: string
  expires_at: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
}

export interface VehicleDocument {
  id: number
  type: string
  status: string
  expires_at: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
}

export interface RideLocation {
  lat: number
  lng: number
  label: string
}

export interface PublicUser {
  id: number
  name: string
  avatar_url: string | null
  gender: string
  rating: number
  trips_completed: number
  no_shows: number
  identity_verified: boolean
  phone_verified: boolean
  member_since: string | null
  bio?: string | null
  preferred_vehicle_type?: string | null
  rating_count?: number
}

export interface RideRequest {
  id: number
  status: string
  origin: RideLocation
  destination: RideLocation
  route_path: [number, number][] | null
  distance_m: number
  duration_s: number
  depart_at: string
  depart_window_min: number
  seats_needed: number
  pool_max_seats: number
  accepting_copassengers: boolean
  budget_per_seat_poisha: number | null
  women_only: boolean
  transport_preference: string
  notes: string | null
  expires_at: string | null
  join_requests_count?: number
  active_trip?: { id: number; status: string } | null
  creator?: PublicUser
  created_at: string
}

export interface RideOffer {
  id: number
  status: string
  origin: RideLocation
  destination: RideLocation
  route_path: [number, number][] | null
  distance_m: number
  duration_s: number
  depart_at: string
  depart_window_min: number
  seats_total: number
  seats_allocated: number
  seats_available: number
  cost_total_poisha: number
  provider_participates: boolean
  women_only: boolean
  notes: string | null
  expires_at: string | null
  provider?: PublicUser
  vehicle?: Vehicle
  created_at: string
}

export interface JoinTarget {
  id: number
  status: string
  origin: RideLocation
  destination: RideLocation
  depart_at: string
  seats_needed?: number
  seats_available?: number
  cost_total_poisha?: number
}

export interface JoinRequest {
  id: number
  role: string
  seats: number
  status: string
  message: string | null
  user?: { id: number; name: string; rating: number; trips_completed: number }
  consented_contribution_poisha: number | null
  intended_depart_at: string | null
  decided_at: string | null
  expires_at: string | null
  created_at: string
  ride_request?: JoinTarget | null
  ride_offer?: JoinTarget | null
  vehicle?: { id: number; make: string; model: string; registration_no: string } | null
}

export interface TripParticipant {
  id: number
  role: string
  seats: number
  status: string
  consented_fare_version: number
  joined_at: string | null
  user?: { id: number; name: string; avatar_url: string | null; rating: number; trips_completed: number }
}

export interface Trip {
  id: number
  status: string
  transport_mode: string
  source: 'request' | 'offer'
  ride_request_id: number | null
  ride_offer_id: number | null
  pool_capacity: number | null
  seats_allocated: number | null
  seats_in_trip: number
  meeting: { lat: number | null; lng: number | null; label: string | null }
  origin: RideLocation | null
  destination: RideLocation | null
  depart_at: string | null
  coordinator?: { id: number; name: string }
  provider?: { id: number; name: string; rating: number } | null
  vehicle?: Vehicle | null
  offer?: RideOffer | null
  request?: RideRequest | null
  participants: TripParticipant[]
  started_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  tracking_enabled: boolean
  tracking_started_at: string | null
  tracking_stopped_at: string | null
  created_at: string
}

export interface MatchMeta {
  pickup_deviation_m: number
  dropoff_deviation_m: number
  overlap_m: number
  detour_m: number
  cos_theta: number
  score: number
  reasons?: string[]
}

export interface RideSearchResult {
  offer: RideOffer
  match: MatchMeta
  estimated_share_poisha: number
  estimated_total_poisha: number
}

export interface TripLocation {
  id: number
  user_id: number | null
  lat: number
  lng: number
  accuracy_m: number | null
  speed_mps: number | null
  heading_deg: number | null
  recorded_at: string | null
}

export interface TripTracking {
  trip_id: number
  tracking_enabled: boolean
  tracking_started_at: string | null
  tracking_stopped_at: string | null
  locations: TripLocation[]
}

export interface TripEvent {
  id: number
  event: string
  meta: Record<string, unknown> | null
  actor: PublicUser | null
  created_at: string | null
}

export interface OfferMatch {
  offer: RideOffer
  match: MatchMeta
  estimated_share_poisha: number
  estimated_total_poisha: number
}

export interface RequestMatch {
  request: RideRequest
  match: MatchMeta
  estimated_share_poisha: number
  estimated_total_poisha: number
}

export interface Paginated<T> {
  data: T[]
  links?: { next: string | null; prev: string | null }
  meta?: { current_page: number; last_page: number; total: number }
}

export interface FareItem {
  id: number
  label: string
  amount_poisha: number
  split_method: string
}

export interface Contribution {
  id: number
  user?: { id: number; name: string }
  kind: string
  amount_poisha: number
  status: string
  note: string | null
  settled_at: string | null
}

export interface FareAdjustmentVote {
  user: { id: number; name: string } | null
  vote: string
  voted_at: string
}

export interface FareAdjustment {
  id: number
  fare_version: number
  new_total_poisha: number
  reason: string | null
  status: string
  proposer?: { id: number; name: string }
  votes: FareAdjustmentVote[]
  resolved_at: string | null
  created_at: string
}

export interface Fare {
  id: number
  trip_id: number
  status: string
  fare_version: number
  estimated_total_poisha: number
  agreed_total_poisha: number | null
  actual_total_poisha: number | null
  participant_count: number
  provider_participates: boolean
  rounding_absorbed_poisha: number
  collector?: { id: number; name: string } | null
  items: FareItem[]
  contributions: Contribution[]
  adjustments: FareAdjustment[]
  agreed_at: string | null
  settled_at: string | null
  created_at: string
}

export interface ChatMessage {
  id: number
  conversation_id: number
  type: string
  body: string
  sender?: { id: number; name: string; avatar_url: string | null }
  mine: boolean
  created_at: string
}

export interface Conversation {
  id: number
  trip_id: number
  unread_count: number
  last_read_message_id: number | null
  trip: {
    id: number
    status: string
    source: 'request' | 'offer'
    origin: RideLocation | null
    destination: RideLocation | null
    depart_at: string | null
  } | null
  participants: { id: number; name: string; avatar_url: string | null }[]
  latest_message: ChatMessage | null
  created_at: string
}

export interface AppNotification {
  id: string
  type: string
  title: string
  body: string
  url: string | null
  meta: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface NotificationList extends Paginated<AppNotification> {
  meta?: {
    current_page: number
    last_page: number
    total: number
    unread_count: number
  }
}

export interface AdminStats {
  users: { total: number; active: number; suspended: number }
  pending_verifications: number
  pending_vehicle_documents: number
  pending_vehicles: number
  open_reports: number
  trips: { in_progress: number; completed: number }
}

export interface AdminVerificationDocument {
  id: number
  type: string
  status: string
  expires_at: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
  file_url: string
  user?: {
    id: number
    name: string
    phone: string
    nid_verified_at: string | null
    license_verified_at: string | null
  }
  reviewer?: { id: number; name: string } | null
}

export interface AdminVehicleDocument {
  id: number
  type: string
  status: string
  expires_at: string | null
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string
  file_url: string
  vehicle?: {
    id: number
    make: string
    model: string
    registration_no: string
    status: string
    owner: { id: number; name: string; phone: string }
  }
  reviewer?: { id: number; name: string } | null
}

export interface Report {
  id: number
  category: string
  description: string
  status: string
  resolution_note: string | null
  resolved_at: string | null
  created_at: string
  reporter?: { id: number; name: string; phone: string }
  reported_user?: { id: number; name: string; phone: string; status: string } | null
  trip_id: number | null
  reviewer?: { id: number; name: string } | null
}
