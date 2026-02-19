import {
  ConflictException,
  HttpException,
  HttpStatus,
  Injectable,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import { UserRole } from '@prisma/client';
import * as bcrypt from 'bcrypt';
import { Response } from 'express';
import { MailService } from '../mail/mail.service';
import { PrismaService } from '../prisma/prisma.service';
import { UsersService } from '../users/users.service';
import { LoginDto } from './dto/login.dto';
import { RegisterDto } from './dto/register.dto';
import { RequestOtpDto } from './dto/request-otp.dto';
import { ResetPasswordDto } from './dto/reset-password.dto';
import { VerifyOtpDto } from './dto/verify-otp.dto';

const COOKIE_NAME = 'pelisnube_token';

type AuthPayload = {
  sub: string;
  email: string;
  role: UserRole;
};

type PasswordResetPayload = {
  sub: string;
  type: 'PASSWORD_RESET';
};

@Injectable()
export class AuthService {
  private readonly otpExpMinutes: number;
  private readonly otpMaxAttempts: number;
  private readonly otpResendSeconds: number;

  constructor(
    private readonly prisma: PrismaService,
    private readonly usersService: UsersService,
    private readonly jwtService: JwtService,
    private readonly configService: ConfigService,
    private readonly mailService: MailService,
  ) {
    this.otpExpMinutes = Number(this.configService.get<string>('OTP_EXP_MINUTES') ?? '10');
    this.otpMaxAttempts = Number(this.configService.get<string>('OTP_MAX_ATTEMPTS') ?? '5');
    this.otpResendSeconds = Number(this.configService.get<string>('OTP_RESEND_SECONDS') ?? '60');
  }

  async register(dto: RegisterDto) {
    const email = dto.email.toLowerCase().trim();
    const existingUser = await this.prisma.user.findUnique({ where: { email } });

    if (existingUser) {
      throw new ConflictException('Este correo ya esta registrado.');
    }

    const adminCount = await this.usersService.countAdmins();
    const role: UserRole = adminCount === 0 ? 'ADMIN' : 'USER';

    const passwordHash = await bcrypt.hash(dto.password, 10);
    const user = await this.prisma.user.create({
      data: {
        name: dto.name.trim(),
        email,
        passwordHash,
        role,
      },
      select: {
        id: true,
        name: true,
        email: true,
        role: true,
        createdAt: true,
      },
    });

    const token = await this.signAccessToken({
      sub: user.id,
      email: user.email,
      role: user.role,
    });

    return { token, user };
  }

  async login(dto: LoginDto) {
    const email = dto.email.toLowerCase().trim();
    const user = await this.prisma.user.findUnique({ where: { email } });

    if (!user) {
      throw new UnauthorizedException('Credenciales invalidas.');
    }

    const isPasswordValid = await bcrypt.compare(dto.password, user.passwordHash);
    if (!isPasswordValid) {
      throw new UnauthorizedException('Credenciales invalidas.');
    }

    const token = await this.signAccessToken({
      sub: user.id,
      email: user.email,
      role: user.role,
    });

    return {
      token,
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
        createdAt: user.createdAt,
      },
    };
  }

  async getMe(userId: string) {
    const user = await this.usersService.findProfileById(userId);
    if (!user) {
      throw new UnauthorizedException('Sesion no valida.');
    }
    return user;
  }

  async requestPasswordOtp(dto: RequestOtpDto) {
    const email = dto.email.toLowerCase().trim();
    const user = await this.prisma.user.findUnique({ where: { email } });

    if (!user) {
      return {
        message:
          'Si el correo existe, te enviaremos un codigo de recuperacion en unos segundos.',
      };
    }

    const latestOtp = await this.prisma.passwordOtp.findFirst({
      where: {
        userId: user.id,
        isUsed: false,
      },
      orderBy: {
        createdAt: 'desc',
      },
    });

    if (latestOtp) {
      const secondsSinceLatestOtp =
        (Date.now() - latestOtp.createdAt.getTime()) / 1000;
      if (secondsSinceLatestOtp < this.otpResendSeconds) {
        throw new HttpException(
          `Espera ${this.otpResendSeconds} segundos antes de solicitar otro codigo.`,
          HttpStatus.TOO_MANY_REQUESTS,
        );
      }
    }

    const code = String(Math.floor(100000 + Math.random() * 900000));
    const codeHash = await bcrypt.hash(code, 10);

    await this.prisma.passwordOtp.create({
      data: {
        userId: user.id,
        codeHash,
        expiresAt: new Date(Date.now() + this.otpExpMinutes * 60 * 1000),
        attempts: 0,
        isUsed: false,
      },
    });

    await this.mailService.sendOtpMail(user.email, code);

    return {
      message:
        'Si el correo existe, te enviaremos un codigo de recuperacion en unos segundos.',
    };
  }

  async verifyPasswordOtp(dto: VerifyOtpDto) {
    const email = dto.email.toLowerCase().trim();
    const user = await this.prisma.user.findUnique({ where: { email } });

    if (!user) {
      throw new UnauthorizedException('Codigo invalido o expirado.');
    }

    const otp = await this.prisma.passwordOtp.findFirst({
      where: {
        userId: user.id,
        isUsed: false,
      },
      orderBy: {
        createdAt: 'desc',
      },
    });

    if (!otp || otp.expiresAt.getTime() < Date.now()) {
      throw new UnauthorizedException('Codigo invalido o expirado.');
    }

    if (otp.attempts >= this.otpMaxAttempts) {
      throw new HttpException(
        'Demasiados intentos. Solicita un nuevo codigo.',
        HttpStatus.TOO_MANY_REQUESTS,
      );
    }

    const isCodeValid = await bcrypt.compare(dto.code, otp.codeHash);
    if (!isCodeValid) {
      await this.prisma.passwordOtp.update({
        where: { id: otp.id },
        data: { attempts: { increment: 1 } },
      });
      throw new UnauthorizedException('Codigo invalido o expirado.');
    }

    await this.prisma.passwordOtp.update({
      where: { id: otp.id },
      data: { isUsed: true },
    });

    const resetToken = await this.jwtService.signAsync(
      {
        sub: user.id,
        type: 'PASSWORD_RESET',
      } satisfies PasswordResetPayload,
      {
        secret: this.configService.getOrThrow<string>('JWT_SECRET'),
        expiresIn: '15m',
      },
    );

    return { resetToken };
  }

  async resetPassword(dto: ResetPasswordDto) {
    let payload: PasswordResetPayload;

    try {
      payload = await this.jwtService.verifyAsync<PasswordResetPayload>(dto.token, {
        secret: this.configService.getOrThrow<string>('JWT_SECRET'),
      });
    } catch {
      throw new UnauthorizedException('Token de recuperacion invalido o vencido.');
    }

    if (payload.type !== 'PASSWORD_RESET') {
      throw new UnauthorizedException('Token de recuperacion invalido o vencido.');
    }

    const newPasswordHash = await bcrypt.hash(dto.newPassword, 10);

    await this.prisma.user.update({
      where: { id: payload.sub },
      data: { passwordHash: newPasswordHash },
    });

    return { message: 'Contrasena actualizada correctamente.' };
  }

  async signAccessToken(payload: AuthPayload): Promise<string> {
    const expiresIn = this.configService.get<string>('JWT_EXPIRES_IN') ?? '7d';
    return this.jwtService.signAsync(payload, { expiresIn: expiresIn as never });
  }

  attachAuthCookie(response: Response, token: string): void {
    response.cookie(COOKIE_NAME, token, this.getCookieOptions());
  }

  clearAuthCookie(response: Response): void {
    response.clearCookie(COOKIE_NAME, this.getCookieOptions());
  }

  private getCookieOptions() {
    const isProduction = this.configService.get<string>('NODE_ENV') === 'production';
    const cookieDomain = this.configService.get<string>('COOKIE_DOMAIN') ?? undefined;

    return {
      httpOnly: true,
      secure: isProduction,
      sameSite: 'lax' as const,
      domain: cookieDomain,
      path: '/',
    };
  }
}
