import { Injectable, NotFoundException } from '@nestjs/common';
import { PromoRecipientStatus, Prisma } from '@prisma/client';
import { slugify } from '../common/utils/slug.util';
import { MailService } from '../mail/mail.service';
import { PrismaService } from '../prisma/prisma.service';
import { CreateContentDto } from './dto/create-content.dto';
import { SendPromotionDto } from './dto/send-promotion.dto';
import { UpdateContentDto } from './dto/update-content.dto';
import { UpsertSectionDto } from './dto/upsert-section.dto';

@Injectable()
export class AdminService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly mailService: MailService,
  ) {}

  async createContent(dto: CreateContentDto) {
    const slug = await this.getUniqueSlug(dto.title);

    const content = await this.prisma.content.create({
      data: {
        title: dto.title.trim(),
        slug,
        type: dto.type,
        synopsis: dto.synopsis.trim(),
        year: dto.year,
        duration: dto.duration,
        rating: dto.rating,
        posterUrl: dto.posterUrl,
        bannerUrl: dto.bannerUrl,
        trailerUrl: dto.trailerUrl,
        isActive: dto.isActive ?? true,
      },
      select: { id: true },
    });

    if (dto.sectionIds && dto.sectionIds.length > 0) {
      await this.replaceContentSections(content.id, dto.sectionIds);
    }

    return this.getContentById(content.id);
  }

  async updateContent(contentIdOrSlug: string, dto: UpdateContentDto) {
    const content = await this.findContentEntity(contentIdOrSlug);
    if (!content) {
      throw new NotFoundException('Contenido no encontrado.');
    }

    const data: Prisma.ContentUpdateInput = {
      title: dto.title?.trim(),
      type: dto.type,
      synopsis: dto.synopsis?.trim(),
      year: dto.year,
      duration: dto.duration,
      rating: dto.rating,
      posterUrl: dto.posterUrl,
      bannerUrl: dto.bannerUrl,
      trailerUrl: dto.trailerUrl,
      isActive: dto.isActive,
    };

    if (dto.title && dto.title.trim() !== content.title) {
      data.slug = await this.getUniqueSlug(dto.title, content.id);
    }

    await this.prisma.content.update({
      where: { id: content.id },
      data,
    });

    if (dto.sectionIds) {
      await this.replaceContentSections(content.id, dto.sectionIds);
    }

    return this.getContentById(content.id);
  }

  async deleteContent(contentIdOrSlug: string) {
    const content = await this.findContentEntity(contentIdOrSlug);
    if (!content) {
      throw new NotFoundException('Contenido no encontrado.');
    }

    await this.prisma.content.delete({
      where: { id: content.id },
    });

    return {
      message: 'Contenido eliminado.',
      contentId: content.id,
    };
  }

  async upsertSection(dto: UpsertSectionDto) {
    const section = dto.id
      ? await this.prisma.section.update({
          where: { id: dto.id },
          data: {
            key: dto.key,
            name: dto.name,
            description: dto.description,
            isHomeVisible: dto.isHomeVisible,
            sortOrder: dto.sortOrder,
          },
          select: { id: true },
        })
      : await this.prisma.section.upsert({
          where: { key: dto.key },
          update: {
            name: dto.name,
            description: dto.description,
            isHomeVisible: dto.isHomeVisible,
            sortOrder: dto.sortOrder,
          },
          create: {
            key: dto.key,
            name: dto.name,
            description: dto.description,
            isHomeVisible: dto.isHomeVisible,
            sortOrder: dto.sortOrder,
          },
          select: { id: true },
        });

    if (dto.contentIds) {
      await this.replaceSectionContents(section.id, dto.contentIds);
    }

    return this.prisma.section.findUnique({
      where: { id: section.id },
      select: {
        id: true,
        key: true,
        name: true,
        description: true,
        isHomeVisible: true,
        sortOrder: true,
        contents: {
          orderBy: { sortOrder: 'asc' },
          select: {
            sortOrder: true,
            content: {
              select: {
                id: true,
                title: true,
                slug: true,
                posterUrl: true,
              },
            },
          },
        },
      },
    });
  }

  async sendPromotion(userId: string, dto: SendPromotionDto) {
    const activeSubscribers = await this.prisma.subscription.findMany({
      where: { status: 'ACTIVE' },
      select: {
        userId: true,
        user: {
          select: {
            email: true,
          },
        },
      },
    });

    const campaign = await this.prisma.promoCampaign.create({
      data: {
        subject: dto.subject,
        htmlBody: dto.htmlBody,
        createdBy: userId,
      },
    });

    let sentCount = 0;
    let failedCount = 0;

    for (const subscriber of activeSubscribers) {
      let status: PromoRecipientStatus = 'SENT';
      let error: string | null = null;

      try {
        await this.mailService.sendPromotionMail(
          subscriber.user.email,
          dto.subject,
          dto.htmlBody,
        );
        sentCount += 1;
      } catch (mailError) {
        status = 'FAILED';
        failedCount += 1;
        error = mailError instanceof Error ? mailError.message : 'Error desconocido al enviar correo';
      }

      await this.prisma.promoRecipient.create({
        data: {
          campaignId: campaign.id,
          userId: subscriber.userId,
          email: subscriber.user.email,
          sentAt: status === 'SENT' ? new Date() : null,
          status,
          error,
        },
      });
    }

    return {
      campaignId: campaign.id,
      totalRecipients: activeSubscribers.length,
      sentCount,
      failedCount,
    };
  }

  async listPromotions() {
    const campaigns = await this.prisma.promoCampaign.findMany({
      orderBy: {
        createdAt: 'desc',
      },
      select: {
        id: true,
        subject: true,
        createdAt: true,
        creator: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
        recipients: {
          select: {
            email: true,
            status: true,
            sentAt: true,
            error: true,
          },
        },
      },
    });

    return campaigns.map((campaign) => ({
      id: campaign.id,
      subject: campaign.subject,
      createdAt: campaign.createdAt,
      createdBy: campaign.creator,
      totalRecipients: campaign.recipients.length,
      sentCount: campaign.recipients.filter((recipient) => recipient.status === 'SENT').length,
      failedCount: campaign.recipients.filter((recipient) => recipient.status === 'FAILED').length,
      recipients: campaign.recipients,
    }));
  }

  async getCatalogForAdmin() {
    return this.prisma.content.findMany({
      orderBy: [{ createdAt: 'desc' }],
      select: {
        id: true,
        title: true,
        slug: true,
        type: true,
        synopsis: true,
        year: true,
        duration: true,
        rating: true,
        isActive: true,
        posterUrl: true,
        bannerUrl: true,
        trailerUrl: true,
        sections: {
          select: {
            section: {
              select: {
                id: true,
                key: true,
                name: true,
              },
            },
          },
        },
      },
    });
  }

  async getSectionsForAdmin() {
    return this.prisma.section.findMany({
      orderBy: [{ sortOrder: 'asc' }, { name: 'asc' }],
      select: {
        id: true,
        key: true,
        name: true,
        description: true,
        isHomeVisible: true,
        sortOrder: true,
        contents: {
          select: {
            contentId: true,
          },
        },
      },
    });
  }

  private async replaceContentSections(contentId: string, sectionIds: string[]) {
    await this.prisma.contentSection.deleteMany({
      where: { contentId },
    });

    const uniqueSectionIds = [...new Set(sectionIds)];

    for (let index = 0; index < uniqueSectionIds.length; index += 1) {
      const sectionId = uniqueSectionIds[index];
      await this.prisma.contentSection.create({
        data: {
          contentId,
          sectionId,
          sortOrder: index,
        },
      });
    }
  }

  private async replaceSectionContents(sectionId: string, contentIds: string[]) {
    await this.prisma.contentSection.deleteMany({
      where: { sectionId },
    });

    const uniqueContentIds = [...new Set(contentIds)];

    for (let index = 0; index < uniqueContentIds.length; index += 1) {
      const contentId = uniqueContentIds[index];
      await this.prisma.contentSection.create({
        data: {
          sectionId,
          contentId,
          sortOrder: index,
        },
      });
    }
  }

  private async getUniqueSlug(title: string, excludeId?: string): Promise<string> {
    const baseSlug = slugify(title);
    let slug = baseSlug;
    let counter = 1;

    while (true) {
      const existing = await this.prisma.content.findFirst({
        where: {
          slug,
          ...(excludeId ? { id: { not: excludeId } } : {}),
        },
        select: {
          id: true,
        },
      });

      if (!existing) {
        return slug;
      }

      slug = `${baseSlug}-${counter}`;
      counter += 1;
    }
  }

  private async findContentEntity(contentIdOrSlug: string) {
    return this.prisma.content.findFirst({
      where: {
        OR: [{ id: contentIdOrSlug }, { slug: contentIdOrSlug }],
      },
      select: {
        id: true,
        title: true,
      },
    });
  }

  private async getContentById(contentId: string) {
    return this.prisma.content.findUnique({
      where: { id: contentId },
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
        sections: {
          orderBy: {
            sortOrder: 'asc',
          },
          select: {
            sortOrder: true,
            section: {
              select: {
                id: true,
                key: true,
                name: true,
              },
            },
          },
        },
      },
    });
  }
}
