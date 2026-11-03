
// Translation utility function
const translateValue = (list, value) => {
  const cleanValue = value?.toLowerCase()
  
  return list.value.find(item => item.value.toLowerCase() === cleanValue)?.title || value
}

export function useTranslation() {
  return {
    translateValue,
  }
}
