import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class UsersService {
  constructor(private readonly prisma: PrismaService) {}

  async findProfileById(userId: string) {
    return this.prisma.user.findUnique({
      where: { id: userId },
      select: {
        id: true,
        name: true,
        email: true,
        role: true,
        createdAt: true,
        subscription: {
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
        },
      },
    });
  }

  async countAdmins(): Promise<number> {
    return this.prisma.user.count({ where: { role: 'ADMIN' } });
  }
}
