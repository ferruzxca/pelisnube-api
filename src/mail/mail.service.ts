import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import nodemailer, { Transporter } from 'nodemailer';

@Injectable()
export class MailService {
  private readonly logger = new Logger(MailService.name);
  private readonly transporter: Transporter | null;
  private readonly from: string;

  constructor(private readonly configService: ConfigService) {
    const host = this.configService.get<string>('SMTP_HOST');
    const port = Number(this.configService.get<string>('SMTP_PORT') ?? '587');
    const user = this.configService.get<string>('SMTP_USER');
    const pass = this.configService.get<string>('SMTP_PASS');

    this.from = this.configService.get<string>('SMTP_FROM') ?? 'PelisNube <no-reply@pelisnube.local>';

    if (host && user && pass) {
      this.transporter = nodemailer.createTransport({
        host,
        port,
        secure: port === 465,
        auth: { user, pass },
      });
    } else {
      this.transporter = null;
      this.logger.warn(
        'SMTP no configurado. Los correos se registraran en consola para modo local.',
      );
    }
  }

  async sendOtpMail(to: string, code: string): Promise<void> {
    const subject = 'Codigo de recuperacion - PelisNube';
    const html = `
      <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111;">
        <h2 style="margin-bottom: 8px;">Recuperacion de contrasena</h2>
        <p>Tu codigo OTP es:</p>
        <p style="font-size: 26px; font-weight: 700; letter-spacing: 6px;">${code}</p>
        <p>El codigo expira en 10 minutos.</p>
      </div>
    `;

    await this.sendMail(to, subject, html);
  }

  async sendPromotionMail(to: string, subject: string, htmlBody: string): Promise<void> {
    await this.sendMail(to, subject, htmlBody);
  }

  private async sendMail(to: string, subject: string, html: string): Promise<void> {
    if (!this.transporter) {
      this.logger.log(`Correo simulado para ${to}. Asunto: ${subject}`);
      return;
    }

    await this.transporter.sendMail({
      from: this.from,
      to,
      subject,
      html,
    });
  }
}
