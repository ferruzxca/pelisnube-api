import { Controller, Get } from '@nestjs/common';
import { SectionsService } from './sections.service';

@Controller('sections')
export class SectionsController {
  constructor(private readonly sectionsService: SectionsService) {}

  @Get('home')
  getHomeSections() {
    return this.sectionsService.getHomeSections();
  }
}
