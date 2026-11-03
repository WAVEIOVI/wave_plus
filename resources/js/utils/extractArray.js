/**
 * Utility to consistently extract array data from API responses.
 *
 * Handles differences in pagination vs unpaginated responses.
 *
 * @param {Object|Array} response - The API response object.
 * @returns {Array} The extracted array of data.
 */
export const extractArray = response => {
  if (Array.isArray(response)) return response
  
  return response?.data?.data || response?.data || []
}
