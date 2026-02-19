import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class FavoritesService {
  constructor(private readonly prisma: PrismaService) {}

  async list(userId: string) {
    const favorites = await this.prisma.favorite.findMany({
      where: { userId },
      orderBy: {
        createdAt: 'desc',
      },
      select: {
        createdAt: true,
        content: {
          select: {
            id: true,
            title: true,
            slug: true,
            type: true,
            synopsis: true,
            year: true,
            duration: true,
            rating: true,
            posterUrl: true,
            bannerUrl: true,
            trailerUrl: true,
            isActive: true,
          },
        },
      },
    });

    return favorites
      .filter((item) => item.content.isActive)
      .map((item) => ({
        addedAt: item.createdAt,
        ...item.content,
      }));
  }

  async add(userId: string, contentId: string) {
    const content = await this.prisma.content.findFirst({
      where: {
        OR: [{ id: contentId }, { slug: contentId }],
        isActive: true,
      },
      select: { id: true },
    });

    if (!content) {
      throw new NotFoundException('Contenido no encontrado.');
    }

    await this.prisma.favorite.upsert({
      where: {
        userId_contentId: {
          userId,
          contentId: content.id,
        },
      },
      update: {},
      create: {
        userId,
        contentId: content.id,
      },
    });

    return {
      message: 'Contenido agregado a favoritos.',
      contentId: content.id,
    };
  }

  async remove(userId: string, contentId: string) {
    const content = await this.prisma.content.findFirst({
      where: {
        OR: [{ id: contentId }, { slug: contentId }],
      },
      select: { id: true },
    });

    if (!content) {
      throw new NotFoundException('Contenido no encontrado.');
    }

    await this.prisma.favorite.deleteMany({
      where: {
        userId,
        contentId: content.id,
      },
    });

    return {
      message: 'Contenido removido de favoritos.',
      contentId: content.id,
    };
  }
}
