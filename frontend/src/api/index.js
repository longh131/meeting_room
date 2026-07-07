import axios from '@/utils/axios'

export const authApi = {
  login: (data) => axios.post('/login', data),
  logout: () => axios.post('/logout'),
  me: () => axios.get('/me'),
}

export const meetingRoomApi = {
  list: (params) => axios.get('/meeting-rooms', { params }),
  floors: () => axios.get('/meeting-rooms/floors'),
  get: (id) => axios.get(`/meeting-rooms/${id}`),
  checkAvailability: (data) => axios.post('/meeting-rooms/check-availability', data),
}

export const reservationApi = {
  list: (params) => axios.get('/reservations', { params }),
  get: (id) => axios.get(`/reservations/${id}`),
  create: (data) => axios.post('/reservations', data),
  update: (id, data) => axios.put(`/reservations/${id}`, data),
  remove: (id) => axios.delete(`/reservations/${id}`),
}

export const userApi = {
  list: (params) => axios.get('/users', { params }),
  availableAttendees: (params) => axios.get('/users/available-attendees', { params }),
  create: (data) => axios.post('/users', data),
  update: (id, data) => axios.put(`/users/${id}`, data),
  remove: (id) => axios.delete(`/users/${id}`),
}

export const deviceTagApi = {
  list: (params) => axios.get('/device-tags', { params }),
  create: (data) => axios.post('/device-tags', data),
  update: (id, data) => axios.put(`/device-tags/${id}`, data),
  remove: (id) => axios.delete(`/device-tags/${id}`),
}

export const imConfigApi = {
  get: () => axios.get('/tenants/im-config'),
  update: (data) => axios.put('/tenants/im-config', data),
}

export const favoriteApi = {
  list: () => axios.get('/favorites'),
  add: (meetingRoomId) => axios.post('/favorites', { meeting_room_id: meetingRoomId }),
  remove: (meetingRoomId) => axios.delete(`/favorites/${meetingRoomId}`),
}
