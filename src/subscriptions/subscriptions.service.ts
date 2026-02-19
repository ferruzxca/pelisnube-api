import { Injectable, NotFoundException } from '@nestjs/common';
import { PaymentStatus } from '@prisma/client';
import { getCardBrand, getCardLast4 } from '../common/utils/card.util';
import { PrismaService } from '../prisma/prisma.service';
import { CheckoutDto } from './dto/checkout.dto';

@Injectable()
export class SubscriptionsService {
  constructor(private readonly prisma: PrismaService) {}

  async getPlans() {
    const plans = await this.prisma.subscriptionPlan.findMany({
      orderBy: {
        priceMonthly: 'asc',
      },
    });

    return plans.map((plan) => ({
      id: plan.id,
      code: plan.code,
      name: plan.name,
      priceMonthly: Number(plan.priceMonthly),
      quality: plan.quality,
      screens: plan.screens,
    }));
  }

  async getMySubscription(userId: string) {
    const subscription = await this.prisma.subscription.findUnique({
      where: { userId },
      select: {
        id: true,
        status: true,
        startedAt: true,
        renewalAt: true,
        endedAt: true,
        plan: {
          select: {
            id: true,
            code: true,
            name: true,
            priceMonthly: true,
            quality: true,
            screens: true,
          },
        },
      },
    });

    if (!subscription) {
      return null;
    }

    return {
      ...subscription,
      plan: {
        ...subscription.plan,
        priceMonthly: Number(subscription.plan.priceMonthly),
      },
    };
  }

  async checkout(userId: string, dto: CheckoutDto) {
    const plan = await this.prisma.subscriptionPlan.findUnique({ where: { code: dto.planCode } });

    if (!plan) {
      throw new NotFoundException('Plan no encontrado.');
    }

    const last4 = getCardLast4(dto.cardNumber);
    const cardBrand = getCardBrand(dto.cardNumber);
    const paymentStatus: PaymentStatus = last4 === '0000' ? 'FAILED' : 'SUCCESS';

    const paymentAttempt = await this.prisma.paymentAttempt.create({
      data: {
        userId,
        planId: plan.id,
        amount: plan.priceMonthly,
        status: paymentStatus,
        last4,
        cardBrand,
        reason: paymentStatus === 'FAILED' ? 'Tarjeta rechazada por simulador.' : null,
      },
      select: {
        id: true,
        status: true,
        amount: true,
        last4: true,
        cardBrand: true,
        reason: true,
        createdAt: true,
      },
    });

    if (paymentStatus === 'FAILED') {
      return {
        status: 'FAILED',
        message: 'Pago rechazado. Prueba con otra tarjeta.',
        paymentAttempt: {
          ...paymentAttempt,
          amount: Number(paymentAttempt.amount),
        },
      };
    }

    const now = new Date();
    const renewalAt = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);

    const subscription = await this.prisma.subscription.upsert({
      where: { userId },
      update: {
        planId: plan.id,
        status: 'ACTIVE',
        startedAt: now,
        renewalAt,
        endedAt: null,
      },
      create: {
        userId,
        planId: plan.id,
        status: 'ACTIVE',
        startedAt: now,
        renewalAt,
      },
      select: {
        id: true,
        status: true,
        startedAt: true,
        renewalAt: true,
        endedAt: true,
        plan: {
          select: {
            code: true,
            name: true,
            priceMonthly: true,
            quality: true,
            screens: true,
          },
        },
      },
    });

    return {
      status: 'SUCCESS',
      message: 'Suscripcion activada correctamente.',
      paymentAttempt: {
        ...paymentAttempt,
        amount: Number(paymentAttempt.amount),
      },
      subscription: {
        ...subscription,
        plan: {
          ...subscription.plan,
          priceMonthly: Number(subscription.plan.priceMonthly),
        },
      },
    };
  }

  async cancel(userId: string) {
    const subscription = await this.prisma.subscription.findUnique({ where: { userId } });

    if (!subscription || subscription.status !== 'ACTIVE') {
      return {
        message: 'No hay suscripcion activa para cancelar.',
      };
    }

    const updated = await this.prisma.subscription.update({
      where: { userId },
      data: {
        status: 'CANCELED',
        endedAt: new Date(),
      },
      select: {
        id: true,
        status: true,
        startedAt: true,
        renewalAt: true,
        endedAt: true,
      },
    });

    return {
      message: 'Suscripcion cancelada.',
      subscription: updated,
    };
  }
}
