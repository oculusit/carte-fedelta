export const auth = {
  isLoggedIn() { return false },
  isAdmin() { return false },
  isModerator() { return false },
  canManageStores() { return false },
  canModerateUsers() { return false },
  getUserId() { return null },
  getUserEmail() { return '' },
  logout() {},
  async login() { throw new Error('Auth disabilitata') },
  async register() { throw new Error('Auth disabilitata') },
}
