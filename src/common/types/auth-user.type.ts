export type AuthUser = {
  sub: string;
  email: string;
  role: 'USER' | 'ADMIN';
};
