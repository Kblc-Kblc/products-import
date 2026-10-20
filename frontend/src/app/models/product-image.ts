export interface ProductImage {
  /** Original link from the import file */
  url: string;
  /** Local path in storage; null if the download failed */
  path: string | null;
  /** Public URL of the local copy */
  src: string | null;
}
